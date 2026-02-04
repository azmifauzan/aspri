<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $trialDays = 30
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = SystemSetting::getValue('app_name', config('app.name'));
        $aspriName = $notifiable->profile?->aspri_name ?? 'ASPRI';
        $callPreference = $notifiable->profile?->call_preference ?? 'Kak';

        return (new MailMessage)
            ->subject("Selamat Datang di {$appName}! 🎉")
            ->greeting("Halo {$callPreference} {$notifiable->name}!")
            ->line("Terima kasih telah bergabung dengan {$appName}. Kami senang Anda ada di sini!")
            ->line("Anda telah mendapatkan masa percobaan gratis selama {$this->trialDays} hari dengan akses ke semua fitur {$aspriName}.")
            ->line('Berikut yang bisa Anda lakukan:')
            ->line('• 💬 Chat dengan asisten AI untuk kelola jadwal dan keuangan')
            ->line('• 📅 Atur jadwal dengan reminder otomatis')
            ->line('• 💰 Catat dan analisis keuangan Anda')
            ->line('• 📱 Hubungkan dengan Telegram untuk akses di mana saja')
            ->action('Mulai Sekarang', url('/dashboard'))
            ->line("Selamat menikmati {$appName}!");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'trial_days' => $this->trialDays,
        ];
    }
}
