<?php

namespace Tests\Feature\Integration;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);
    }

    public function test_dashboard_displays_financial_summary(): void
    {
        // Buat akun keuangan
        $account = FinanceAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'initial_balance' => 0,
        ]);

        // Buat kategori
        $incomeCategory = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Salary',
            'tx_type' => 'income',
        ]);

        $expenseCategory = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Food',
            'tx_type' => 'expense',
        ]);

        // Buat transaksi bulan ini
        FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'tx_type' => 'income',
            'amount' => 5000000,
            'note' => 'Monthly Salary',
            'occurred_at' => now(),
        ]);

        FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
            'tx_type' => 'expense',
            'amount' => 150000,
            'note' => 'Lunch',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('monthlyFinance')
            ->where('monthlyFinance.income', 5000000)
            ->where('monthlyFinance.expenses', 150000)
            ->where('monthlyFinance.balance', 4850000)
        );
    }

    public function test_dashboard_displays_todays_schedule(): void
    {
        // Buat jadwal hari ini
        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Team Meeting',
            'description' => 'Weekly sync',
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'is_completed' => false,
        ]);

        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Lunch Break',
            'description' => null,
            'start_time' => now()->setTime(12, 0),
            'end_time' => now()->setTime(13, 0),
            'is_completed' => false,
        ]);

        // Buat jadwal kemarin (tidak tampil)
        Schedule::create([
            'user_id' => $this->user->id,
            'title' => 'Yesterday Event',
            'start_time' => now()->subDay()->setTime(10, 0),
            'end_time' => now()->subDay()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('todaySchedule', 2)
            ->where('todaySchedule.0.title', 'Team Meeting')
            ->where('todaySchedule.1.title', 'Lunch Break')
        );
    }

    public function test_dashboard_shows_empty_state_for_new_user(): void
    {
        $newUser = User::factory()->create();
        $newUser->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $this->actingAs($newUser);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('monthlyFinance.income', 0)
            ->where('monthlyFinance.expenses', 0)
            ->where('monthlyFinance.balance', 0)
            ->has('todaySchedule', 0)
        );
    }

    public function test_dashboard_only_shows_user_own_data(): void
    {
        // User lain dengan data sendiri
        $otherUser = User::factory()->create();
        $otherUser->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $otherAccount = FinanceAccount::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Cash',
            'type' => 'cash',
            'initial_balance' => 0,
        ]);

        $otherCategory = FinanceCategory::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Income',
            'tx_type' => 'income',
        ]);

        FinanceTransaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => $otherCategory->id,
            'tx_type' => 'income',
            'amount' => 9999999,
            'note' => 'Should not appear',
            'occurred_at' => now(),
        ]);

        Schedule::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Meeting',
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'is_completed' => false,
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('monthlyFinance.income', 0) // Not 9999999
            ->has('todaySchedule', 0) // Not other user's schedule
        );
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_dashboard_displays_user_profile_info(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user', fn ($user) => $user
                ->where('id', $this->user->id)
                ->where('name', $this->user->name)
                ->where('email', $this->user->email)
                ->etc()
            )
        );
    }
}
