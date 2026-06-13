<?php

namespace Tests\Unit;

use App\Services\Ai\ResponseTemplates;
use PHPUnit\Framework\TestCase;

class ResponseTemplatesTest extends TestCase
{
    public function test_detect_language_returns_en_for_english_text(): void
    {
        $this->assertSame('en', ResponseTemplates::detectLanguage('What is my balance today?'));
    }

    public function test_detect_language_returns_id_for_indonesian_text(): void
    {
        $this->assertSame('id', ResponseTemplates::detectLanguage('Berapa saldo saya hari ini?'));
    }

    public function test_detect_language_defaults_to_id_for_ambiguous_or_empty_text(): void
    {
        $this->assertSame('id', ResponseTemplates::detectLanguage(''));
        $this->assertSame('id', ResponseTemplates::detectLanguage('123456'));
    }

    public function test_rupiah_formats_amount_with_thousands_separator(): void
    {
        $this->assertSame('Rp5.000.000', ResponseTemplates::rupiah(5000000));
        $this->assertSame('Rp0', ResponseTemplates::rupiah(0));
        $this->assertSame('Rp50.000', ResponseTemplates::rupiah(50000));
    }

    public function test_period_label_maps_known_periods(): void
    {
        $this->assertSame('hari ini', ResponseTemplates::periodLabel('today', 'id'));
        $this->assertSame('today', ResponseTemplates::periodLabel('today', 'en'));
        $this->assertSame('besok', ResponseTemplates::periodLabel('tomorrow', 'id'));
        $this->assertSame('tomorrow', ResponseTemplates::periodLabel('tomorrow', 'en'));
        $this->assertSame('minggu ini', ResponseTemplates::periodLabel('this_week', 'id'));
        $this->assertSame('this week', ResponseTemplates::periodLabel('this_week', 'en'));
        $this->assertSame('bulan ini', ResponseTemplates::periodLabel('this_month', 'id'));
        $this->assertSame('this month', ResponseTemplates::periodLabel('this_month', 'en'));
        // Unknown period falls back to "this month" label.
        $this->assertSame('bulan ini', ResponseTemplates::periodLabel('unknown_period', 'id'));
        $this->assertSame('this month', ResponseTemplates::periodLabel('unknown_period', 'en'));
    }

    public function test_confirm_footer_contains_ya_and_batal_in_indonesian(): void
    {
        $footer = ResponseTemplates::confirmFooter('id');

        $this->assertStringContainsString('"ya"', $footer);
        $this->assertStringContainsString('"batal"', $footer);
    }

    public function test_confirm_footer_contains_yes_and_cancel_in_english(): void
    {
        $footer = ResponseTemplates::confirmFooter('en');

        $this->assertStringContainsString('"yes"', $footer);
        $this->assertStringContainsString('"cancel"', $footer);
    }

    public function test_finance_summary_renders_amounts_via_rupiah(): void
    {
        $summary = [
            'period' => 'this_month',
            'income' => 5000000,
            'expense' => 3000000,
            'net' => 2000000,
            'total_balance' => 10000000,
        ];

        $result = ResponseTemplates::financeSummary($summary, 'Kak Budi', 'id');

        $this->assertStringContainsString('Rp5.000.000', $result);
        $this->assertStringContainsString('Rp3.000.000', $result);
        $this->assertStringContainsString('Rp2.000.000', $result);
        $this->assertStringContainsString('Rp10.000.000', $result);
        $this->assertStringContainsString('Kak Budi', $result);
        $this->assertStringContainsString('bulan ini', $result);
    }

    public function test_finance_summary_renders_in_english(): void
    {
        $summary = [
            'period' => 'today',
            'income' => 1000000,
            'expense' => 250000,
            'net' => 750000,
            'total_balance' => 9000000,
        ];

        $result = ResponseTemplates::financeSummary($summary, 'Bro John', 'en');

        $this->assertStringContainsString('Rp1.000.000', $result);
        $this->assertStringContainsString('Rp250.000', $result);
        $this->assertStringContainsString('Rp750.000', $result);
        $this->assertStringContainsString('Rp9.000.000', $result);
        $this->assertStringContainsString('today', $result);
        $this->assertStringContainsString('Bro John', $result);
    }

    public function test_transaction_confirmation_contains_nominal_category_and_footer(): void
    {
        $payload = [
            'tx_type' => 'expense',
            'amount' => 50000,
            'category' => 'Makanan',
        ];

        $result = ResponseTemplates::transactionConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('Rp50.000', $result);
        $this->assertStringContainsString('Makanan', $result);
        $this->assertStringContainsString('"ya"', $result);
        $this->assertStringContainsString('"batal"', $result);
        $this->assertStringContainsString('Pengeluaran', $result);
    }

    public function test_transaction_confirmation_handles_missing_category_and_note(): void
    {
        $payload = [
            'tx_type' => 'income',
            'amount' => 1500000,
        ];

        $result = ResponseTemplates::transactionConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('Rp1.500.000', $result);
        $this->assertStringContainsString('Belum ditentukan', $result);
        $this->assertStringContainsString('Pemasukan', $result);
    }

    public function test_transaction_confirmation_in_english(): void
    {
        $payload = [
            'tx_type' => 'income',
            'amount' => 200000,
            'category' => 'Salary',
            'note' => 'Monthly salary',
        ];

        $result = ResponseTemplates::transactionConfirmation($payload, 'Bro John', 'en');

        $this->assertStringContainsString('Rp200.000', $result);
        $this->assertStringContainsString('Salary', $result);
        $this->assertStringContainsString('Income', $result);
        $this->assertStringContainsString('Monthly salary', $result);
        $this->assertStringContainsString('"yes"', $result);
        $this->assertStringContainsString('"cancel"', $result);
    }

    public function test_schedule_confirmation_contains_title_and_footer(): void
    {
        $payload = [
            'title' => 'Meeting dengan klien',
            'start_time' => '2026-06-15 10:00',
            'end_time' => '2026-06-15 11:00',
            'location' => 'Kantor Pusat',
        ];

        $result = ResponseTemplates::scheduleConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('Meeting dengan klien', $result);
        $this->assertStringContainsString('2026-06-15 10:00', $result);
        $this->assertStringContainsString('2026-06-15 11:00', $result);
        $this->assertStringContainsString('Kantor Pusat', $result);
        $this->assertStringContainsString('"ya"', $result);
        $this->assertStringContainsString('"batal"', $result);
    }

    public function test_schedule_confirmation_handles_missing_location(): void
    {
        $payload = [
            'title' => 'Olahraga pagi',
            'start_time' => '2026-06-15 06:00',
        ];

        $result = ResponseTemplates::scheduleConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('Olahraga pagi', $result);
        $this->assertStringContainsString('2026-06-15 06:00', $result);
        $this->assertStringContainsString('-', $result);
    }

    public function test_schedule_update_confirmation_lists_changed_fields(): void
    {
        $payload = [
            'title' => 'Meeting Lama',
            'new_title' => 'Meeting Baru',
            'start_time' => '2026-06-16 09:00',
            'location' => 'Ruang Rapat 2',
        ];

        $result = ResponseTemplates::scheduleUpdateConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('Meeting Lama', $result);
        $this->assertStringContainsString('Meeting Baru', $result);
        $this->assertStringContainsString('2026-06-16 09:00', $result);
        $this->assertStringContainsString('Ruang Rapat 2', $result);
        $this->assertStringContainsString('"ya"', $result);
        $this->assertStringContainsString('"batal"', $result);
    }

    public function test_schedule_update_confirmation_falls_back_to_schedule_id(): void
    {
        $payload = [
            'schedule_id' => 42,
            'new_title' => 'Updated title',
        ];

        $result = ResponseTemplates::scheduleUpdateConfirmation($payload, 'Kak Budi', 'id');

        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('Updated title', $result);
    }

    public function test_delete_confirmation_contains_warning_identifier_and_ya(): void
    {
        $result = ResponseTemplates::deleteConfirmation('jadwal', 'Meeting dengan klien', 'Kak Budi', 'id');

        $this->assertStringContainsStringIgnoringCase('peringatan', $result);
        $this->assertStringContainsString('Meeting dengan klien', $result);
        $this->assertStringContainsString('jadwal', $result);
        $this->assertStringContainsString('"ya"', $result);
        $this->assertStringContainsString('"batal"', $result);
    }

    public function test_delete_confirmation_in_english_contains_warning(): void
    {
        $result = ResponseTemplates::deleteConfirmation('schedule', 'Client meeting', 'Bro John', 'en');

        $this->assertStringContainsStringIgnoringCase('warning', $result);
        $this->assertStringContainsString('Client meeting', $result);
        $this->assertStringContainsString('schedule', $result);
        $this->assertStringContainsString('"yes"', $result);
        $this->assertStringContainsString('"cancel"', $result);
    }

    public function test_schedules_list_renders_all_items(): void
    {
        $schedules = [
            [
                'id' => 1,
                'title' => 'Meeting Pagi',
                'start_time' => '15 Jun 2026 09:00',
                'end_time' => '10:00',
                'location' => 'Kantor',
            ],
            [
                'id' => 2,
                'title' => 'Janji Dokter',
                'start_time' => '16 Jun 2026 14:00',
                'end_time' => null,
                'location' => null,
            ],
        ];

        $result = ResponseTemplates::schedulesList($schedules, 'this_week', 'Kak Budi', 'id');

        $this->assertStringContainsString('Meeting Pagi', $result);
        $this->assertStringContainsString('Janji Dokter', $result);
        $this->assertStringContainsString('15 Jun 2026 09:00', $result);
        $this->assertStringContainsString('10:00', $result);
        $this->assertStringContainsString('Kantor', $result);
        $this->assertStringContainsString('Kak Budi', $result);
    }

    public function test_schedules_list_empty_state_with_period(): void
    {
        $result = ResponseTemplates::schedulesList([], 'today', 'Kak Budi', 'id');

        $this->assertStringContainsString('Kak Budi', $result);
        $this->assertStringContainsString('hari ini', $result);
        $this->assertStringNotContainsString('Meeting', $result);
    }

    public function test_schedules_list_empty_state_without_period(): void
    {
        $result = ResponseTemplates::schedulesList([], null, 'Kak Budi', 'id');

        $this->assertStringContainsString('Kak Budi', $result);
        $this->assertStringNotContainsString('Meeting', $result);
    }

    public function test_notes_list_renders_all_items(): void
    {
        $notes = [
            [
                'id' => 1,
                'title' => 'Ide Bisnis',
                'content' => 'Buka toko kue online dengan target pasar anak muda.',
                'tags' => ['bisnis', 'ide'],
                'created_at' => '13 Jun 2026',
            ],
            [
                'id' => 2,
                'title' => 'Belanja Bulanan',
                'content' => 'Beli beras, minyak, telur, dan sayuran.',
                'tags' => null,
                'created_at' => '12 Jun 2026',
            ],
        ];

        $result = ResponseTemplates::notesList($notes, 'Kak Budi', 'id');

        $this->assertStringContainsString('Ide Bisnis', $result);
        $this->assertStringContainsString('Belanja Bulanan', $result);
        $this->assertStringContainsString('bisnis, ide', $result);
        $this->assertStringContainsString('Kak Budi', $result);
    }

    public function test_notes_list_truncates_long_content(): void
    {
        $longContent = str_repeat('a', 150);

        $notes = [
            [
                'id' => 1,
                'title' => 'Catatan Panjang',
                'content' => $longContent,
                'tags' => null,
                'created_at' => '13 Jun 2026',
            ],
        ];

        $result = ResponseTemplates::notesList($notes, 'Kak Budi', 'id');

        $this->assertStringContainsString('...', $result);
        $this->assertStringNotContainsString($longContent, $result);
    }

    public function test_notes_list_empty_state(): void
    {
        $result = ResponseTemplates::notesList([], 'Kak Budi', 'id');

        $this->assertStringContainsString('Kak Budi', $result);
        $this->assertStringContainsString('belum ada catatan', $result);
    }

    public function test_notes_list_empty_state_english(): void
    {
        $result = ResponseTemplates::notesList([], 'Bro John', 'en');

        $this->assertStringContainsString('Bro John', $result);
        $this->assertStringContainsString("don't have any notes", $result);
    }

    public function test_cancellation_message_in_indonesian(): void
    {
        $result = ResponseTemplates::cancellation('Kak Budi', 'id');

        $this->assertStringContainsString('Kak Budi', $result);
        $this->assertStringContainsStringIgnoringCase('batal', $result);
    }

    public function test_cancellation_message_in_english(): void
    {
        $result = ResponseTemplates::cancellation('Bro John', 'en');

        $this->assertStringContainsString('Bro John', $result);
        $this->assertStringContainsStringIgnoringCase('cancel', $result);
    }
}
