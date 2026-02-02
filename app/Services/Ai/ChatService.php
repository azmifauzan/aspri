<?php

namespace App\Services\Ai;

use App\Models\User;

class ChatService
{
    protected AiProviderInterface $provider;

    public function __construct(AiProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Build the system prompt based on user's persona settings.
     */
    public function buildSystemPrompt(User $user): string
    {
        $profile = $user->profile;

        $callPreference = $profile?->call_preference ?? 'Kak';
        $aspriName = $profile?->aspri_name ?? 'ASPRI';
        $aspriPersona = $profile?->aspri_persona ?? 'asisten yang ramah dan membantu';

        $currentDate = now()->format('l, d F Y');
        $currentTime = now()->format('H:i');

        return <<<PROMPT
Kamu adalah {$aspriName}, {$aspriPersona}. 
Kamu adalah asisten pribadi berbasis AI untuk membantu mengelola jadwal dan keuangan harian.

Informasi pengguna:
- Nama: {$user->name}
- Panggilan: {$callPreference} {$user->name}

Informasi waktu:
- Tanggal: {$currentDate}
- Waktu: {$currentTime}

Kemampuan yang kamu miliki:
1. Membantu mencatat dan mengelola transaksi keuangan (pemasukan/pengeluaran)
2. Membantu mengatur jadwal dan pengingat
3. Memberikan ringkasan keuangan bulanan
4. Menjawab pertanyaan umum dengan ramah

Panduan komunikasi:
- Selalu gunakan panggilan "{$callPreference}" saat berbicara dengan pengguna
- Gunakan bahasa Indonesia yang santai tapi tetap sopan
- Berikan respons yang singkat dan jelas
- Jika diminta melakukan sesuatu yang tidak bisa kamu lakukan, jelaskan dengan sopan

Untuk transaksi keuangan, jika pengguna ingin mencatat:
- Tanyakan detail yang diperlukan (jumlah, kategori, deskripsi) jika tidak disebutkan
- Konfirmasi sebelum menyimpan data

Untuk jadwal, jika pengguna ingin membuat:
- Tanyakan waktu dan judul acara jika tidak disebutkan
- Konfirmasi sebelum menyimpan
PROMPT;
    }

    /**
     * Format messages for AI provider.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function formatMessages(User $user, string $userMessage, array $conversationHistory = []): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($user),
            ],
        ];

        // Add conversation history (limit to last 10 exchanges)
        $historyLimit = array_slice($conversationHistory, -20);
        foreach ($historyLimit as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        return $messages;
    }

    /**
     * Send a message and get a response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function sendMessage(User $user, string $message, array $conversationHistory = []): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory);

        return $this->provider->chat($messages);
    }

    /**
     * Send a message and stream the response.
     *
     * @param  array<int, array{role: string, content: string}>  $conversationHistory
     */
    public function streamMessage(User $user, string $message, callable $callback, array $conversationHistory = []): string
    {
        $messages = $this->formatMessages($user, $message, $conversationHistory);

        return $this->provider->chatStream($messages, $callback);
    }
}
