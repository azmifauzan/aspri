<?php

namespace Tests\Feature\Integration;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FinanceAccount $account;

    private FinanceCategory $incomeCategory;

    private FinanceCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $this->account = FinanceAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Cash Wallet',
            'type' => 'cash',
            'initial_balance' => 1000000,
        ]);

        $this->incomeCategory = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Salary',
            'tx_type' => 'income',
            'icon' => '💰',
        ]);

        $this->expenseCategory = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Food',
            'tx_type' => 'expense',
            'icon' => '🍔',
        ]);
    }

    public function test_user_can_view_finance_overview(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('finance'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Index')
        );
    }

    public function test_user_can_create_income_transaction(): void
    {
        $this->actingAs($this->user);

        $transactionData = [
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'income',
            'amount' => 500000,
            'note' => 'Freelance project',
        ];

        $response = $this->post(route('finance.transactions.store'), $transactionData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $this->user->id,
            'amount' => 500000,
            'tx_type' => 'income',
            'note' => 'Freelance project',
        ]);
    }

    public function test_user_can_create_expense_transaction(): void
    {
        $this->actingAs($this->user);

        $transactionData = [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 50000,
            'note' => 'Lunch at restaurant',
        ];

        $response = $this->post(route('finance.transactions.store'), $transactionData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $this->user->id,
            'amount' => 50000,
            'tx_type' => 'expense',
            'note' => 'Lunch at restaurant',
        ]);
    }

    public function test_user_can_update_transaction(): void
    {
        $this->actingAs($this->user);

        $transaction = FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 100000,
            'note' => 'Shopping',
        ]);

        $updateData = [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 150000,
            'note' => 'Shopping (updated)',
        ];

        $response = $this->put(route('finance.transactions.update', $transaction), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_transactions', [
            'id' => $transaction->id,
            'amount' => 150000,
            'note' => 'Shopping (updated)',
        ]);
    }

    public function test_user_can_delete_transaction(): void
    {
        $this->actingAs($this->user);

        $transaction = FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 75000,
            'note' => 'To be deleted',
        ]);

        $response = $this->delete(route('finance.transactions.destroy', $transaction));

        $response->assertRedirect();
        $this->assertDatabaseMissing('finance_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_user_can_create_category(): void
    {
        $this->actingAs($this->user);

        $categoryData = [
            'name' => 'Transportation',
            'tx_type' => 'expense',
            'icon' => '🚗',
        ];

        $response = $this->post(route('finance.categories.store'), $categoryData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_categories', [
            'user_id' => $this->user->id,
            'name' => 'Transportation',
            'tx_type' => 'expense',
        ]);
    }

    public function test_user_can_update_category(): void
    {
        $this->actingAs($this->user);

        $category = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Entertainment',
            'tx_type' => 'expense',
            'icon' => '🎬',
        ]);

        $updateData = [
            'name' => 'Entertainment & Hobbies',
            'tx_type' => 'expense',
            'icon' => '🎮',
        ];

        $response = $this->put(route('finance.categories.update', $category), $updateData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_categories', [
            'id' => $category->id,
            'name' => 'Entertainment & Hobbies',
            'icon' => '🎮',
        ]);
    }

    public function test_user_can_delete_category_without_transactions(): void
    {
        $this->actingAs($this->user);

        $category = FinanceCategory::create([
            'user_id' => $this->user->id,
            'name' => 'Unused Category',
            'tx_type' => 'expense',
            'icon' => '📦',
        ]);

        $response = $this->delete(route('finance.categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('finance_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_user_cannot_delete_category_with_transactions(): void
    {
        $this->actingAs($this->user);

        // Category dengan transaksi
        FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 50000,
            'note' => 'Using this category',
        ]);

        $response = $this->delete(route('finance.categories.destroy', $this->expenseCategory));

        // Should fail or redirect with error
        $this->assertDatabaseHas('finance_categories', [
            'id' => $this->expenseCategory->id,
        ]);
    }

    public function test_user_can_create_account(): void
    {
        $this->actingAs($this->user);

        $accountData = [
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => 5000000,
        ];

        $response = $this->post(route('finance.accounts.store'), $accountData);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_accounts', [
            'user_id' => $this->user->id,
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => 5000000,
        ]);
    }

    public function test_user_can_view_transactions_list(): void
    {
        $this->actingAs($this->user);

        // Create multiple transactions
        FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->incomeCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'income',
            'amount' => 1000000,
            'note' => 'Income 1',
        ]);

        FinanceTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => 50000,
            'note' => 'Expense 1',
        ]);

        $response = $this->get(route('finance.transactions'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('finance/Transactions')
        );
    }

    public function test_user_cannot_access_other_users_transactions(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = FinanceAccount::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Account',
            'type' => 'cash',
            'initial_balance' => 1000,
        ]);

        $otherCategory = FinanceCategory::create([
            'user_id' => $otherUser->id,
            'name' => 'Other Category',
            'tx_type' => 'expense',
        ]);

        $otherTransaction = FinanceTransaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'category_id' => $otherCategory->id,
            'tx_type' => 'expense',
            'amount' => 100,
            'note' => 'Other user transaction',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user);

        // Try to update other user's transaction
        $response = $this->put(route('finance.transactions.update', $otherTransaction), [
            'amount' => 999999,
        ]);

        // Should be forbidden or not found
        $response->assertStatus(403);
    }

    public function test_transaction_requires_valid_amount(): void
    {
        $this->actingAs($this->user);

        $transactionData = [
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'occurred_at' => now(),
            'tx_type' => 'expense',
            'amount' => -50000, // Negative amount
            'note' => 'Invalid transaction',
        ];

        $response = $this->post(route('finance.transactions.store'), $transactionData);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_transaction_requires_category(): void
    {
        $this->actingAs($this->user);

        $transactionData = [
            'account_id' => $this->account->id,
            'tx_type' => 'expense',
            'amount' => 50000,
            'note' => 'No category transaction',
            'occurred_at' => now(),
            // Missing category_id
        ];

        $response = $this->post(route('finance.transactions.store'), $transactionData);

        $response->assertSessionHasErrors(['category_id']);
    }
}
