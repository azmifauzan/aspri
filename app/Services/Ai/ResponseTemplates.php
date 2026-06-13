<?php

namespace App\Services\Ai;

/**
 * Deterministic, non-LLM response templates.
 *
 * Renders data-heavy replies (summaries, lists, confirmations) without a
 * second LLM round-trip, guaranteeing that numbers and identifiers are
 * never altered or hallucinated by the model.
 *
 * All methods are static — this class is a stateless collection of pure
 * string-building helpers and is not meant to be instantiated.
 */
class ResponseTemplates
{
    /**
     * Indonesian marker/stopword list used for language detection.
     *
     * @var array<int, string>
     */
    private const ID_MARKERS = [
        'yang', 'dan', 'saya', 'kamu', 'ini', 'itu', 'dengan', 'tidak', 'untuk',
        'apa', 'bagaimana', 'lihat', 'catat', 'berapa', 'mau', 'bisa', 'ada',
        'hari', 'ya', 'tolong',
    ];

    /**
     * English marker word list used for language detection.
     *
     * @var array<int, string>
     */
    private const EN_MARKERS = [
        'the', 'is', 'are', 'you', 'my', 'what', 'how', 'please', 'show',
        'today', 'balance', 'schedule', 'note', 'and', 'for', 'with', 'this',
        'that', 'can', 'do', 'does',
    ];

    /**
     * Detect whether a message is written in Indonesian ('id') or English ('en').
     *
     * Defaults to 'id' for empty, ambiguous, or Indonesian-leaning text.
     * Only returns 'en' when English marker matches strictly outnumber
     * Indonesian marker matches.
     */
    public static function detectLanguage(string $text): string
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($text))) ?: [];
        $words = array_filter($words, fn ($w) => $w !== '');

        $idMatches = 0;
        $enMatches = 0;

        foreach ($words as $word) {
            $word = trim($word, ".,!?;:\"'()");

            if (in_array($word, self::ID_MARKERS, true)) {
                $idMatches++;
            }

            if (in_array($word, self::EN_MARKERS, true)) {
                $enMatches++;
            }
        }

        if ($enMatches > $idMatches && $enMatches > 0) {
            return 'en';
        }

        return 'id';
    }

    /**
     * Format an amount as Indonesian Rupiah, e.g. `rupiah(5000000) === 'Rp5.000.000'`.
     */
    public static function rupiah(int|float $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Map a period key (e.g. 'today', 'this_month') to a human-readable label.
     */
    public static function periodLabel(string $period, string $lang = 'id'): string
    {
        $labels = [
            'today' => ['id' => 'hari ini', 'en' => 'today'],
            'tomorrow' => ['id' => 'besok', 'en' => 'tomorrow'],
            'this_week' => ['id' => 'minggu ini', 'en' => 'this week'],
            'this_month' => ['id' => 'bulan ini', 'en' => 'this month'],
        ];

        $entry = $labels[$period] ?? $labels['this_month'];

        return $entry[$lang] ?? $entry['id'];
    }

    /**
     * The standard "reply yes/cancel to confirm" footer for confirmation messages.
     */
    public static function confirmFooter(string $lang = 'id'): string
    {
        if ($lang === 'en') {
            return 'Reply "yes" to confirm or "cancel" to cancel.';
        }

        return 'Balas "ya" untuk konfirmasi atau "batal" untuk membatalkan.';
    }

    /**
     * Render a finance summary, as returned by `ActionExecutorService::getFinanceSummary()`.
     *
     * @param  array{period: string, income: float|int, expense: float|int, net: float|int, total_balance: float|int}  $summary
     */
    public static function financeSummary(array $summary, string $callName, string $lang = 'id'): string
    {
        $period = self::periodLabel($summary['period'] ?? 'this_month', $lang);

        $income = self::rupiah($summary['income'] ?? 0);
        $expense = self::rupiah($summary['expense'] ?? 0);
        $net = self::rupiah($summary['net'] ?? 0);
        $totalBalance = self::rupiah($summary['total_balance'] ?? 0);

        if ($lang === 'en') {
            return "{$callName}, here's your financial summary for {$period}:\n"
                ."- Income: {$income}\n"
                ."- Expense: {$expense}\n"
                ."- Net: {$net}\n"
                ."- Total Balance: {$totalBalance}";
        }

        return "{$callName}, ini ringkasan keuangan {$period}:\n"
            ."- Pemasukan: {$income}\n"
            ."- Pengeluaran: {$expense}\n"
            ."- Selisih: {$net}\n"
            ."- Saldo Total: {$totalBalance}";
    }

    /**
     * Render a confirmation message for a `create_transaction` tool call.
     *
     * @param  array{tx_type: string, amount: int|float, category?: ?string, note?: ?string, occurred_at?: ?string}  $payload
     */
    public static function transactionConfirmation(array $payload, string $callName, string $lang = 'id'): string
    {
        $isIncome = ($payload['tx_type'] ?? null) === 'income';
        $amount = self::rupiah($payload['amount'] ?? 0);
        $category = $payload['category'] ?? null;
        $note = $payload['note'] ?? null;

        if ($lang === 'en') {
            $typeLabel = $isIncome ? 'Income' : 'Expense';
            $category = $category ?? 'Not specified';

            $lines = [
                "{$callName}, please confirm this transaction:",
                "- Type: {$typeLabel}",
                "- Amount: {$amount}",
                "- Category: {$category}",
            ];

            if ($note !== null) {
                $lines[] = "- Note: {$note}";
            }

            $lines[] = '';
            $lines[] = self::confirmFooter($lang);

            return implode("\n", $lines);
        }

        $typeLabel = $isIncome ? 'Pemasukan' : 'Pengeluaran';
        $category = $category ?? 'Belum ditentukan';

        $lines = [
            "{$callName}, mohon konfirmasi transaksi berikut:",
            "- Jenis: {$typeLabel}",
            "- Nominal: {$amount}",
            "- Kategori: {$category}",
        ];

        if ($note !== null) {
            $lines[] = "- Catatan: {$note}";
        }

        $lines[] = '';
        $lines[] = self::confirmFooter($lang);

        return implode("\n", $lines);
    }

    /**
     * Render a confirmation message for a `create_schedule` tool call.
     *
     * @param  array{title: string, start_time: string, end_time?: ?string, location?: ?string, description?: ?string}  $payload
     */
    public static function scheduleConfirmation(array $payload, string $callName, string $lang = 'id'): string
    {
        $title = $payload['title'] ?? '-';
        $startTime = $payload['start_time'] ?? '-';
        $endTime = $payload['end_time'] ?? null;
        $location = $payload['location'] ?? '-';
        $description = $payload['description'] ?? null;

        $timeRange = $endTime !== null ? "{$startTime} - {$endTime}" : $startTime;

        if ($lang === 'en') {
            $lines = [
                "{$callName}, please confirm this schedule:",
                "- Title: {$title}",
                "- Time: {$timeRange}",
                "- Location: {$location}",
            ];

            if ($description !== null) {
                $lines[] = "- Description: {$description}";
            }

            $lines[] = '';
            $lines[] = self::confirmFooter($lang);

            return implode("\n", $lines);
        }

        $lines = [
            "{$callName}, mohon konfirmasi jadwal berikut:",
            "- Judul: {$title}",
            "- Waktu: {$timeRange}",
            "- Lokasi: {$location}",
        ];

        if ($description !== null) {
            $lines[] = "- Deskripsi: {$description}";
        }

        $lines[] = '';
        $lines[] = self::confirmFooter($lang);

        return implode("\n", $lines);
    }

    /**
     * Render a confirmation message for an `update_schedule` tool call, listing
     * only the fields that are actually being changed.
     *
     * @param  array{schedule_id?: ?int, title?: ?string, new_title?: ?string, start_time?: ?string, end_time?: ?string, location?: ?string, description?: ?string}  $payload
     */
    public static function scheduleUpdateConfirmation(array $payload, string $callName, string $lang = 'id'): string
    {
        $identifier = $payload['title'] ?? $payload['schedule_id'] ?? ($lang === 'en' ? 'that schedule' : 'jadwal tersebut');

        $fieldLabels = $lang === 'en'
            ? [
                'new_title' => 'New title',
                'start_time' => 'New start time',
                'end_time' => 'New end time',
                'location' => 'New location',
                'description' => 'New description',
            ]
            : [
                'new_title' => 'Judul baru',
                'start_time' => 'Waktu mulai baru',
                'end_time' => 'Waktu selesai baru',
                'location' => 'Lokasi baru',
                'description' => 'Deskripsi baru',
            ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            if (isset($payload[$field])) {
                $changes[] = "- {$label}: {$payload[$field]}";
            }
        }

        if (empty($changes)) {
            $changes[] = $lang === 'en' ? '- No changes specified' : '- Tidak ada detail perubahan';
        }

        $changesText = implode("\n", $changes);

        if ($lang === 'en') {
            return "{$callName}, please confirm the update to \"{$identifier}\":\n"
                ."{$changesText}\n\n"
                .self::confirmFooter($lang);
        }

        return "{$callName}, mohon konfirmasi perubahan jadwal \"{$identifier}\":\n"
            ."{$changesText}\n\n"
            .self::confirmFooter($lang);
    }

    /**
     * Render a confirmation message before deleting an item. Always includes
     * an explicit warning that the action is destructive and irreversible.
     */
    public static function deleteConfirmation(string $itemType, string $identifier, string $callName, string $lang = 'id'): string
    {
        if ($lang === 'en') {
            return "{$callName}, WARNING: you are about to delete this {$itemType}: \"{$identifier}\". This action cannot be undone.\n\n"
                .self::confirmFooter($lang);
        }

        return "{$callName}, PERINGATAN: kamu akan menghapus {$itemType}: \"{$identifier}\". Tindakan ini tidak dapat dibatalkan.\n\n"
            .self::confirmFooter($lang);
    }

    /**
     * Render a list of schedules, as returned by `ActionExecutorService::getSchedules()`.
     *
     * @param  array<int, array{id: int, title: string, start_time: string, end_time?: ?string, location?: ?string}>  $schedules
     */
    public static function schedulesList(array $schedules, ?string $period, string $callName, string $lang = 'id'): string
    {
        if (empty($schedules)) {
            if ($period !== null) {
                $label = self::periodLabel($period, $lang);

                return $lang === 'en'
                    ? "{$callName}, you don't have any schedules for {$label}."
                    : "{$callName}, tidak ada jadwal {$label}.";
            }

            return $lang === 'en'
                ? "{$callName}, you don't have any schedules yet."
                : "{$callName}, belum ada jadwal.";
        }

        $heading = $lang === 'en'
            ? ($period !== null ? "{$callName}, here are your schedules for ".self::periodLabel($period, $lang).':' : "{$callName}, here are your schedules:")
            : ($period !== null ? "{$callName}, ini jadwal kamu untuk ".self::periodLabel($period, $lang).':' : "{$callName}, ini jadwal kamu:");

        $lines = [$heading];

        foreach ($schedules as $schedule) {
            $title = $schedule['title'] ?? '-';
            $startTime = $schedule['start_time'] ?? '-';
            $endTime = $schedule['end_time'] ?? null;
            $location = $schedule['location'] ?? null;

            $timeRange = $endTime !== null ? "{$startTime} - {$endTime}" : $startTime;

            $line = "- {$title} ({$timeRange})";

            if ($location !== null) {
                $line .= $lang === 'en' ? " at {$location}" : " di {$location}";
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Render a list of notes, as returned by `ActionExecutorService::getNotes()`.
     *
     * @param  array<int, array{id: int, title: string, content: string, tags?: ?array, created_at: string}>  $notes
     */
    public static function notesList(array $notes, string $callName, string $lang = 'id'): string
    {
        if (empty($notes)) {
            return $lang === 'en'
                ? "{$callName}, you don't have any notes yet."
                : "{$callName}, belum ada catatan tersimpan.";
        }

        $heading = $lang === 'en'
            ? "{$callName}, here are your notes:"
            : "{$callName}, ini catatan kamu:";

        $lines = [$heading];

        foreach ($notes as $note) {
            $title = $note['title'] ?? '-';
            $content = $note['content'] ?? '';

            if (mb_strlen($content) > 100) {
                $content = mb_substr($content, 0, 100).'...';
            }

            $line = "- {$title}: {$content}";

            $tags = $note['tags'] ?? null;
            if (! empty($tags)) {
                $tagsText = implode(', ', $tags);
                $line .= $lang === 'en' ? " (Tags: {$tagsText})" : " (Tags: {$tagsText})";
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Render a short message confirming that the pending action was cancelled.
     */
    public static function cancellation(string $callName, string $lang = 'id'): string
    {
        if ($lang === 'en') {
            return "Okay {$callName}, cancelled.";
        }

        return "Oke {$callName}, dibatalkan ya.";
    }
}
