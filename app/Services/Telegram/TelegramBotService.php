<?php

namespace App\Services\Telegram;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Ai\ChatOrchestrator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;

class TelegramBotService
{
    protected Api $telegram;

    public function __construct(
        protected ChatOrchestrator $chatOrchestrator,
        protected SettingsService $settingsService,
    ) {
        // Get bot token from database first, then fallback to config/env
        // Check if system_settings table exists (for migrations)
        $botToken = null;
        if (Schema::hasTable('system_settings')) {
            $botToken = $this->settingsService->get('telegram_bot_token');
        }

        // Fallback to config/env if not in database
        if (! $botToken) {
            $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        }

        $this->telegram = new Api($botToken);

        // Disable SSL verification if configured
        if (config('services.telegram.http_client_verify') === false) {
            $this->telegram->setHttpClientHandler(
                new \Telegram\Bot\HttpClients\GuzzleHttpClient(
                    new \GuzzleHttp\Client(['verify' => false])
                )
            );
        }
    }

    /**
     * Process incoming Telegram update.
     */
    public function processUpdate(Update $update): void
    {
        $message = $update->getMessage();

        if (! $message) {
            Log::info('Telegram update has no message', ['update' => $update->toArray()]);

            return;
        }

        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $telegramUser = $message->getFrom();

        if (! $text) {
            $this->sendMessage($chatId, 'Maaf, saat ini saya hanya bisa memproses pesan teks.');

            return;
        }

        // Handle /start command
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($chatId, $telegramUser);

            return;
        }

        // Handle /link command for account linking (legacy)
        if (str_starts_with($text, '/link')) {
            $this->handleLinkCommand($chatId, $text, $telegramUser);

            return;
        }

        // Handle connect command for account linking (new format)
        if (str_starts_with(strtolower($text), 'connect ')) {
            $this->handleConnectCommand($chatId, $text, $telegramUser);

            return;
        }

        // Handle /help command
        if ($text === '/help') {
            $this->handleHelpCommand($chatId);

            return;
        }

        // Send typing indicator immediately for better UX
        $this->sendChatAction($chatId, 'typing');

        // Process regular message with AI
        $this->processMessageWithAi($chatId, $text, $telegramUser);
    }

    /**
     * Handle /start command.
     */
    protected function handleStartCommand(int $chatId, $telegramUser): void
    {
        $firstName = $telegramUser->getFirstName();
        $username = $telegramUser->getUsername();

        // Check if user is already linked
        $user = $this->findUserByTelegramId($chatId);

        if ($user) {
            $this->sendMessage(
                $chatId,
                "Halo {$firstName}! 👋\n\nAkun Telegram kamu sudah terhubung dengan ASPRI.\n\nKamu bisa langsung mengobrol dengan saya untuk:\n• Mencatat pengeluaran\n• Mengatur jadwal\n• Dan banyak lagi!\n\nKetik /help untuk melihat semua perintah."
            );
        } else {
            $this->sendMessage(
                $chatId,
                "Halo {$firstName}! 👋 Selamat datang di ASPRI Bot.\n\nUntuk mulai menggunakan ASPRI, kamu perlu menghubungkan akun Telegram dengan akun ASPRI-mu.\n\n📱 Cara menghubungkan:\n1. Login ke aplikasi ASPRI di web\n2. Buka menu Settings > Telegram\n3. Salin kode yang muncul\n4. Kirim ke bot: connect KODE_KAMU\n\nContoh: connect ABC123\n\nJika belum punya akun ASPRI, silakan daftar di website kami terlebih dahulu."
            );
        }
    }

    /**
     * Handle /link command for account linking.
     */
    protected function handleLinkCommand(int $chatId, string $text, $telegramUser): void
    {
        $parts = explode(' ', $text);
        $code = $parts[1] ?? null;

        if (! $code) {
            $this->sendMessage(
                $chatId,
                "⚠️ Format tidak valid.\n\nGunakan: /link KODE_KAMU\nContoh: /link ABC123"
            );

            return;
        }

        // Find user by link code
        $user = User::where('telegram_link_code', strtoupper($code))
            ->where('telegram_link_expires_at', '>', now())
            ->first();

        if (! $user) {
            $this->sendMessage(
                $chatId,
                "❌ Kode tidak valid atau sudah kadaluarsa.\n\nSilakan generate kode baru dari aplikasi ASPRI."
            );

            return;
        }

        // Link the account
        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $telegramUser->getUsername(),
            'telegram_link_code' => null,
            'telegram_link_expires_at' => null,
        ]);

        $callPreference = $user->profile?->call_preference ?? 'Kak';
        $aspriName = $user->profile?->aspri_name ?? 'ASPRI';

        $this->sendMessage(
            $chatId,
            "✅ Berhasil! Akun Telegram sudah terhubung.\n\nHalo {$callPreference} {$user->name}! Saya {$aspriName}, asisten pribadi kamu.\n\nSekarang kamu bisa:\n• Mencatat pengeluaran dengan mengetik, misal: \"beli kopi 25rb\"\n• Mengatur jadwal dengan mengetik, misal: \"ingatkan meeting jam 3 sore\"\n• Bertanya apa saja!\n\nKetik /help untuk bantuan."
        );
    }

    /**
     * Handle connect command for account linking (new format).
     */
    protected function handleConnectCommand(int $chatId, string $text, $telegramUser): void
    {
        // Extract code from "connect CODE" format
        $code = trim(substr($text, 8)); // Remove "connect " prefix

        if (! $code) {
            $this->sendMessage(
                $chatId,
                "⚠️ Format tidak valid.\n\nGunakan: connect KODE_KAMU\nContoh: connect ABC123"
            );

            return;
        }

        // Find user by link code
        $user = User::where('telegram_link_code', strtoupper($code))
            ->where('telegram_link_expires_at', '>', now())
            ->first();

        if (! $user) {
            $this->sendMessage(
                $chatId,
                "❌ Kode tidak valid atau sudah kadaluarsa.\n\nSilakan generate kode baru dari aplikasi ASPRI."
            );

            return;
        }

        // Link the account
        $user->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $telegramUser->getUsername(),
            'telegram_link_code' => null,
            'telegram_link_expires_at' => null,
        ]);

        $callPreference = $user->profile?->call_preference ?? 'Kak';
        $aspriName = $user->profile?->aspri_name ?? 'ASPRI';

        $this->sendMessage(
            $chatId,
            "✅ Berhasil! Akun Telegram sudah terhubung.\n\nHalo {$callPreference} {$user->name}! Saya {$aspriName}, asisten pribadi kamu.\n\nSekarang kamu bisa:\n• Mencatat pengeluaran dengan mengetik, misal: \"beli kopi 25rb\"\n• Mengatur jadwal dengan mengetik, misal: \"ingatkan meeting jam 3 sore\"\n• Bertanya apa saja!\n\nKetik /help untuk bantuan."
        );
    }

    /**
     * Handle /help command.
     */
    protected function handleHelpCommand(int $chatId): void
    {
        $helpText = "📚 *Bantuan ASPRI Bot*\n\n";
        $helpText .= "*Perintah:*\n";
        $helpText .= "/start - Memulai bot\n";
        $helpText .= "/link KODE - Hubungkan akun Telegram\n";
        $helpText .= "/help - Tampilkan bantuan\n\n";
        $helpText .= "*Contoh penggunaan:*\n";
        $helpText .= "• \"beli kopi 25rb\" - Catat pengeluaran\n";
        $helpText .= "• \"gaji bulan ini 5jt\" - Catat pemasukan\n";
        $helpText .= "• \"meeting jam 3 sore\" - Buat jadwal\n";
        $helpText .= "• \"berapa pengeluaran minggu ini?\" - Cek ringkasan\n";
        $helpText .= "• \"jadwal besok apa?\" - Lihat jadwal\n\n";
        $helpText .= 'Kamu juga bisa mengobrol bebas dengan saya! 😊';

        $this->sendMessage($chatId, $helpText, ['parse_mode' => 'Markdown']);
    }

    /**
     * Process message with AI service.
     */
    protected function processMessageWithAi(int $chatId, string $text, $telegramUser): void
    {
        $user = $this->findUserByTelegramId($chatId);

        if (! $user) {
            $this->sendMessage(
                $chatId,
                "⚠️ Akun Telegram belum terhubung.\n\nKetik /start untuk melihat cara menghubungkan akun."
            );

            return;
        }

        // Send typing indicator
        $this->sendChatAction($chatId, 'typing');

        try {
            // Get or create chat thread for Telegram
            $thread = $this->getOrCreateThread($user);

            // Get conversation history
            $conversationHistory = $thread->messages()
                ->orderBy('created_at')
                ->limit(20)
                ->get()
                ->map(fn (ChatMessage $msg) => [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ])
                ->toArray();

            // Save user message
            $userMessage = $thread->messages()->create([
                'user_id' => $user->id,
                'role' => 'user',
                'content' => $text,
            ]);

            // Send typing indicator again before AI processing (which can take time)
            $this->sendChatAction($chatId, 'typing');

            // Process message through ChatOrchestrator (with intent parsing and persona)
            $result = $this->chatOrchestrator->processMessage($user, $text, $thread, $conversationHistory);

            // Save assistant response
            $thread->messages()->create([
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => $result['response'],
            ]);

            // Update thread timestamp
            $thread->update(['last_message_at' => now()]);

            // Send response to user
            $this->sendMessage($chatId, $result['response']);
        } catch (\Exception $e) {
            Log::error('Error processing Telegram message with AI', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'user_id' => $user->id,
            ]);

            $this->sendMessage(
                $chatId,
                'Maaf, terjadi kesalahan saat memproses pesan. Silakan coba lagi nanti.'
            );
        }
    }

    /**
     * Find user by Telegram chat ID.
     */
    protected function findUserByTelegramId(int $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->first();
    }

    /**
     * Get or create chat thread for Telegram conversations.
     */
    protected function getOrCreateThread(User $user): ChatThread
    {
        // Try to find existing Telegram thread
        $thread = $user->chatThreads()
            ->where('source', 'telegram')
            ->whereDate('last_message_at', today())
            ->first();

        if (! $thread) {
            $thread = $user->chatThreads()->create([
                'title' => 'Telegram - '.now()->format('d M Y'),
                'source' => 'telegram',
                'last_message_at' => now(),
            ]);
        }

        return $thread;
    }

    /**
     * Send a message to a Telegram chat.
     */
    public function sendMessage(int $chatId, string $text, array $options = []): void
    {
        try {
            $this->telegram->sendMessage(array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $options));
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send Telegram message', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    /**
     * Send chat action (typing indicator, etc).
     */
    public function sendChatAction(int $chatId, string $action = 'typing'): void
    {
        try {
            $this->telegram->sendChatAction([
                'chat_id' => $chatId,
                'action' => $action,
            ]);
        } catch (TelegramSDKException $e) {
            Log::warning('Failed to send chat action', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }

    /**
     * Set webhook URL.
     */
    public function setWebhook(string $url): array
    {
        try {
            $response = $this->telegram->setWebhook([
                'url' => $url,
                'secret_token' => config('services.telegram.webhook_secret'),
                'allowed_updates' => ['message', 'callback_query'],
            ]);

            return [
                'success' => true,
                'message' => 'Webhook set successfully',
            ];
        } catch (TelegramSDKException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove webhook.
     */
    public function removeWebhook(): array
    {
        try {
            $this->telegram->removeWebhook();

            return [
                'success' => true,
                'message' => 'Webhook removed successfully',
            ];
        } catch (TelegramSDKException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get webhook info.
     */
    public function getWebhookInfo(): array
    {
        try {
            $info = $this->telegram->getWebhookInfo();

            return [
                'success' => true,
                'data' => $info,
            ];
        } catch (TelegramSDKException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the Telegram API instance.
     */
    public function getApi(): Api
    {
        return $this->telegram;
    }
}
