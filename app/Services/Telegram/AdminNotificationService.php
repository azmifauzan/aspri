<?php

namespace App\Services\Telegram;

use App\Models\PaymentProof;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

class AdminNotificationService
{
    protected ?Api $telegram = null;

    /**
     * Initialize the Telegram API client.
     */
    protected function getTelegram(): ?Api
    {
        if ($this->telegram === null) {
            // Read token from system_settings (encrypted)
            $token = SystemSetting::getValue('telegram_bot_token');
            if (! $token) {
                return null;
            }

            $this->telegram = new Api($token);

            // Disable SSL verification if configured
            if (config('services.telegram.http_client_verify') === false) {
                $this->telegram->setHttpClientHandler(
                    new \Telegram\Bot\HttpClients\GuzzleHttpClient(
                        new \GuzzleHttp\Client(['verify' => false])
                    )
                );
            }
        }

        return $this->telegram;
    }

    /**
     * Get admin Telegram chat IDs from system settings.
     *
     * @return array<int>
     */
    protected function getAdminChatIds(): array
    {
        $chatIds = SystemSetting::getValue('admin_telegram_chat_ids', '');

        if (empty($chatIds)) {
            return [];
        }

        // Parse comma-separated chat IDs
        return array_filter(array_map('intval', explode(',', $chatIds)));
    }

    /**
     * Send message to all admin Telegram accounts.
     */
    public function notifyAdmins(string $message): void
    {
        $telegram = $this->getTelegram();
        if (! $telegram) {
            Log::warning('Telegram bot token not configured for admin notifications');

            return;
        }

        $chatIds = $this->getAdminChatIds();
        if (empty($chatIds)) {
            Log::info('No admin Telegram chat IDs configured');

            return;
        }

        foreach ($chatIds as $chatId) {
            try {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
            } catch (TelegramSDKException $e) {
                Log::error('Failed to send admin notification', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                ]);
            }
        }
    }

    /**
     * Notify admins when a new user registers.
     */
    public function notifyNewUserRegistration(User $user): void
    {
        $message = "🎉 <b>User Baru Terdaftar!</b>\n\n";
        $message .= "👤 <b>Nama:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "📅 <b>Waktu:</b> {$user->created_at->format('d M Y H:i')}\n";

        $this->notifyAdmins($message);
    }

    /**
     * Notify admins when a user uploads payment proof.
     */
    public function notifyNewPaymentProof(PaymentProof $paymentProof): void
    {
        $user = $paymentProof->user;
        $planLabel = $paymentProof->plan_type === 'yearly' ? 'Tahunan' : 'Bulanan';

        $message = "💰 <b>Bukti Pembayaran Baru!</b>\n\n";
        $message .= "👤 <b>User:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "📦 <b>Paket:</b> {$planLabel}\n";
        $message .= '💵 <b>Nominal:</b> Rp '.number_format($paymentProof->amount, 0, ',', '.')."\n";
        $message .= '🏦 <b>Bank:</b> '.($paymentProof->bank_name ?? '-')."\n";
        $message .= "📅 <b>Waktu:</b> {$paymentProof->created_at->format('d M Y H:i')}\n\n";
        $message .= '🔗 Silakan cek di panel admin untuk verifikasi.';

        $this->notifyAdmins($message);
    }

    /**
     * Notify admins when a user's subscription is about to expire.
     */
    public function notifySubscriptionExpiring(User $user, int $daysRemaining): void
    {
        $message = "⏰ <b>Subscription Akan Berakhir</b>\n\n";
        $message .= "👤 <b>User:</b> {$user->name}\n";
        $message .= "📧 <b>Email:</b> {$user->email}\n";
        $message .= "📅 <b>Sisa Waktu:</b> {$daysRemaining} hari\n";

        $this->notifyAdmins($message);
    }
}
