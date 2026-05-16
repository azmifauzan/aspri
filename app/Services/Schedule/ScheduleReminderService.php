<?php

namespace App\Services\Schedule;

use App\Models\EventReminder;
use App\Models\Schedule;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ScheduleReminderService
{
    public function __construct(
        protected TelegramBotService $telegramBot,
    ) {}

    /**
     * Create one or more reminders for a schedule.
     *
     * @param  array<int>  $minutesBeforeList  Minutes before start (e.g. [60, 15] = 1h + 15min before)
     */
    public function createForSchedule(Schedule $schedule, array $minutesBeforeList, string $channel = EventReminder::CHANNEL_APP): Collection
    {
        $created = collect();

        foreach ($minutesBeforeList as $minutesBefore) {
            $minutesBefore = (int) $minutesBefore;

            if ($minutesBefore < 0) {
                continue;
            }

            $scheduledFor = $schedule->start_time->copy()->subMinutes($minutesBefore);

            // Skip reminders that would already be due in the past
            if ($scheduledFor->isPast()) {
                continue;
            }

            $reminder = EventReminder::create([
                'schedule_id' => $schedule->id,
                'user_id' => $schedule->user_id,
                'minutes_before' => $minutesBefore,
                'channel' => $channel,
                'scheduled_for' => $scheduledFor,
            ]);

            $created->push($reminder);
        }

        return $created;
    }

    /**
     * Replace all pending reminders for a schedule.
     *
     * @param  array<int>  $minutesBeforeList
     */
    public function replaceForSchedule(Schedule $schedule, array $minutesBeforeList, string $channel = EventReminder::CHANNEL_APP): Collection
    {
        $schedule->reminders()->pending()->delete();

        return $this->createForSchedule($schedule, $minutesBeforeList, $channel);
    }

    /**
     * Send all due reminders. Returns [sent, failed] counts.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendDue(): array
    {
        $reminders = EventReminder::due()
            ->with(['schedule', 'user'])
            ->orderBy('scheduled_for')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($reminders as $reminder) {
            if (! $reminder->schedule || ! $reminder->user) {
                $reminder->markFailed('Schedule or user missing');
                $failed++;

                continue;
            }

            try {
                $this->deliver($reminder);
                $reminder->markSent();
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Failed to send reminder', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
                $reminder->markFailed($e->getMessage());
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Deliver a reminder via its configured channel.
     */
    protected function deliver(EventReminder $reminder): void
    {
        $channel = $reminder->channel;

        if (in_array($channel, [EventReminder::CHANNEL_TELEGRAM, EventReminder::CHANNEL_BOTH], true)) {
            $this->deliverTelegram($reminder);
        }

        // 'app' delivery is implicit — the reminder row itself is the notification source for the in-app UI.
        // No external action needed; the frontend polls/queries pending reminders.
    }

    protected function deliverTelegram(EventReminder $reminder): void
    {
        $user = $reminder->user;

        if (! $user->telegram_chat_id) {
            throw new \RuntimeException('User has no Telegram chat ID linked');
        }

        $schedule = $reminder->schedule;
        $callPreference = $user->profile?->call_preference ?? 'Kak';

        $text = "🔔 Pengingat untuk {$callPreference} {$user->name}\n\n";
        $text .= "📅 *{$schedule->title}*\n";
        $text .= '🕒 '.$schedule->start_time->translatedFormat('l, d M Y H:i')."\n";

        if ($schedule->location) {
            $text .= "📍 {$schedule->location}\n";
        }

        if ($schedule->description) {
            $text .= "\n{$schedule->description}";
        }

        $this->telegramBot->sendMessage((int) $user->telegram_chat_id, $text, [
            'parse_mode' => 'Markdown',
        ]);
    }
}
