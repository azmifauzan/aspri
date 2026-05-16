<?php

namespace Tests\Feature;

use App\Models\FinanceBudget;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\User;
use App\Services\Finance\FinanceBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FinanceBudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinanceBudgetService;
    }

    public function test_calculate_spent_sums_expenses_in_period(): void
    {
        $user = User::factory()->create();
        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'amount' => 1000000,
        ]);

        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 200000,
            'occurred_at' => '2026-05-10 12:00:00',
        ]);
        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 300000,
            'occurred_at' => '2026-05-20 12:00:00',
        ]);
        // Out of period
        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 999999,
            'occurred_at' => '2026-04-30 23:59:59',
        ]);
        // Income should be excluded
        FinanceTransaction::factory()->income()->create([
            'user_id' => $user->id,
            'amount' => 500000,
            'occurred_at' => '2026-05-15 12:00:00',
        ]);

        $this->assertEquals(500000.0, $this->service->calculateSpent($budget));
    }

    public function test_calculate_spent_filters_by_category_when_set(): void
    {
        $user = User::factory()->create();
        $food = FinanceCategory::factory()->create(['user_id' => $user->id, 'tx_type' => 'expense']);
        $transport = FinanceCategory::factory()->create(['user_id' => $user->id, 'tx_type' => 'expense']);

        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $food->id,
            'period_year' => 2026,
            'period_month' => 5,
        ]);

        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $food->id,
            'amount' => 100000,
            'occurred_at' => '2026-05-10',
        ]);
        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'category_id' => $transport->id,
            'amount' => 500000,
            'occurred_at' => '2026-05-10',
        ]);

        $this->assertEquals(100000.0, $this->service->calculateSpent($budget));
    }

    public function test_progress_reports_over_budget(): void
    {
        $user = User::factory()->create();
        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'amount' => 100000,
            'alert_threshold_pct' => 80,
        ]);

        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 150000,
            'occurred_at' => '2026-05-10',
        ]);

        $progress = $this->service->getProgress($budget);

        $this->assertEquals(150000.0, $progress['spent']);
        $this->assertEquals(-50000.0, $progress['remaining']);
        $this->assertEquals(150.0, $progress['used_pct']);
        $this->assertTrue($progress['is_over']);
        $this->assertFalse($progress['is_approaching']); // already over, not "approaching"
    }

    public function test_progress_reports_approaching_limit(): void
    {
        $user = User::factory()->create();
        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'amount' => 100000,
            'alert_threshold_pct' => 80,
        ]);

        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 85000,
            'occurred_at' => '2026-05-10',
        ]);

        $progress = $this->service->getProgress($budget);

        $this->assertTrue($progress['is_approaching']);
        $this->assertFalse($progress['is_over']);
    }

    public function test_progress_under_threshold(): void
    {
        $user = User::factory()->create();
        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'amount' => 100000,
            'alert_threshold_pct' => 80,
        ]);

        FinanceTransaction::factory()->expense()->create([
            'user_id' => $user->id,
            'amount' => 50000,
            'occurred_at' => '2026-05-10',
        ]);

        $progress = $this->service->getProgress($budget);

        $this->assertFalse($progress['is_approaching']);
        $this->assertFalse($progress['is_over']);
        $this->assertEquals(50.0, $progress['used_pct']);
    }

    public function test_get_progress_for_user_period_only_returns_active_budgets(): void
    {
        $user = User::factory()->create();
        FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'is_active' => true,
        ]);
        FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 5,
            'is_active' => false,
        ]);
        FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'period_year' => 2026,
            'period_month' => 4,
        ]);

        $progress = $this->service->getProgressForUserPeriod($user, 2026, 5);

        $this->assertCount(1, $progress);
    }

    public function test_zero_amount_budget_does_not_divide_by_zero(): void
    {
        $user = User::factory()->create();
        $budget = FinanceBudget::factory()->create([
            'user_id' => $user->id,
            'amount' => 0,
        ]);

        $progress = $this->service->getProgress($budget);

        $this->assertEquals(0.0, $progress['used_pct']);
    }
}
