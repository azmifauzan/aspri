<?php

namespace App\Services\Ai;

use App\Models\User;

class IntentParserService
{
    public function __construct(protected AiProviderInterface $provider) {}

    /**
     * Parse user message to detect intent.
     *
     * @return array{action: string, module: string, entities: array, confidence: float, requires_confirmation: bool}
     */
    public function parse(User $user, string $message, array $conversationHistory = []): array
    {
        $systemPrompt = $this->buildIntentPrompt();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add conversation history for context
        foreach (array_slice($conversationHistory, -6) as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->provider->chat($messages, ['max_tokens' => 512]);

        return $this->parseResponse($response);
    }

    /**
     * Build the system prompt for intent detection.
     */
    protected function buildIntentPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah parser intent untuk asisten pribadi. Tugas kamu adalah menganalisis pesan pengguna dan mengembalikan JSON yang menunjukkan intent mereka.

Format output HARUS berupa JSON valid dengan struktur:
{
  "action": "action_name",
  "module": "module_name",
  "entities": {...},
  "confidence": 0.0-1.0,
  "requires_confirmation": true/false
}

DAFTAR ACTION yang tersedia:

MODULE: finance
- create_transaction: Mencatat transaksi baru (pemasukan/pengeluaran)
  entities: {tx_type: "income"|"expense", amount: number, category: string|null, note: string|null, occurred_at: "YYYY-MM-DD"|null}
- view_balance: Melihat saldo/ringkasan keuangan
  entities: {period: "today"|"this_week"|"this_month"|"all"|null}
- view_transactions: Melihat daftar transaksi
  entities: {period: "today"|"this_week"|"this_month"|null, tx_type: "income"|"expense"|null, limit: number|null}
- delete_transaction: Menghapus transaksi
  entities: {transaction_id: string|null, description: string|null}

MODULE: schedule
- create_schedule: Membuat jadwal/event baru
  entities: {title: string, start_time: "YYYY-MM-DD HH:mm"|null, end_time: "YYYY-MM-DD HH:mm"|null, location: string|null, description: string|null}
- view_schedules: Melihat jadwal
  entities: {period: "today"|"tomorrow"|"this_week"|"this_month"|null}
- delete_schedule: Menghapus jadwal
  entities: {schedule_id: string|null, title: string|null}

MODULE: notes
- create_note: Membuat catatan baru
  entities: {title: string|null, content: string, tags: string[]|null}
- view_notes: Melihat catatan
  entities: {search: string|null, tags: string[]|null, limit: number|null}
- delete_note: Menghapus catatan
  entities: {note_id: string|null, title: string|null}

MODULE: general
- greeting: Sapaan/salam
  entities: {}
- help: Minta bantuan/panduan
  entities: {topic: string|null}
- confirm: Konfirmasi aksi pending (ya, ok, setuju, simpan, dll)
  entities: {}
- cancel: Batalkan aksi pending (tidak, batal, cancel, dll)
  entities: {}
- unknown: Intent tidak dikenali
  entities: {}

ATURAN:
1. Ekstrak semua entities yang bisa ditemukan dari pesan
2. Untuk tanggal/waktu, gunakan format ISO (YYYY-MM-DD atau YYYY-MM-DD HH:mm)
3. "hari ini" = tanggal hari ini, "besok" = tanggal besok
4. Jika jumlah uang disebutkan tanpa "ribu"/"juta", anggap nominal langsung
5. 15rb = 15000, 1.5jt = 1500000
6. requires_confirmation = true untuk semua action yang mengubah data (create, delete)
7. requires_confirmation = false untuk view/read actions dan general
8. confidence menunjukkan seberapa yakin kamu dengan parsing ini (0.0-1.0)
9. Output HANYA JSON, tanpa penjelasan tambahan
PROMPT;
    }

    /**
     * Parse AI response to extract intent data.
     */
    protected function parseResponse(string $response): array
    {
        // Clean response - remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'action' => 'unknown',
                'module' => 'general',
                'entities' => [],
                'confidence' => 0.0,
                'requires_confirmation' => false,
            ];
        }

        return [
            'action' => $data['action'] ?? 'unknown',
            'module' => $data['module'] ?? 'general',
            'entities' => $data['entities'] ?? [],
            'confidence' => (float) ($data['confidence'] ?? 0.5),
            'requires_confirmation' => (bool) ($data['requires_confirmation'] ?? false),
        ];
    }
}
