<?php

namespace Tests\Feature\Integration;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Plugin;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Complete User Journey Integration Test
 *
 * This test simulates a complete user journey from registration
 * to using all features in the application.
 */
class CompleteUserJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function test_complete_user_journey_from_registration_to_all_features(): void
    {
        // ===================================================
        // STEP 1: User Registration
        // ===================================================
        $this->step1_user_registers();

        // ===================================================
        // STEP 2: User Accesses Dashboard
        // ===================================================
        $this->step2_user_accesses_dashboard();

        // ===================================================
        // STEP 3: User Creates Finance Accounts & Categories
        // ===================================================
        $this->step3_user_creates_finance_accounts_and_categories();

        // ===================================================
        // STEP 4: User Records Financial Transactions
        // ===================================================
        $this->step4_user_records_transactions();

        // ===================================================
        // STEP 5: User Manages Schedule
        // ===================================================
        $this->step5_user_manages_schedule();

        // ===================================================
        // STEP 6: User Creates Notes
        // ===================================================
        $this->step6_user_creates_notes();

        // ===================================================
        // STEP 7: User Interacts with Chat Assistant
        // ===================================================
        $this->step7_user_uses_chat_assistant();

        // ===================================================
        // STEP 8: User Activates and Configures Plugins
        // ===================================================
        $this->step8_user_activates_plugins();

        // ===================================================
        // STEP 9: Verify All Data Integrity
        // ===================================================
        $this->step9_verify_data_integrity();

        $this->assertTrue(true, 'Complete user journey completed successfully!');
    }

    private function step1_user_registers(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Ahmad Wijaya',
            'email' => 'ahmad.wijaya@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'call_preference' => 'Pak',
            'aspri_name' => 'Jarvis',
            'aspri_persona' => 'asisten profesional yang sopan',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->user = User::where('email', 'ahmad.wijaya@example.com')->first();
        $this->assertNotNull($this->user);
        $this->assertNotNull($this->user->profile);
        $this->assertEquals('Pak', $this->user->profile->call_preference);
        $this->assertEquals('Jarvis', $this->user->profile->aspri_name);

        // Mark email as verified for testing purposes
        $this->user->markEmailAsVerified();
    }

    private function step2_user_accesses_dashboard(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    private function step3_user_creates_finance_accounts_and_categories(): void
    {
        $this->actingAs($this->user);

        // Create Cash Account
        $this->post(route('finance.accounts.store'), [
            'name' => 'Cash Wallet',
            'type' => 'cash',
            'initial_balance' => 1000000,
        ]);

        // Create Bank Account
        $this->post(route('finance.accounts.store'), [
            'name' => 'Bank BCA',
            'type' => 'bank',
            'initial_balance' => 5000000,
        ]);

        // Create Income Categories
        $this->post(route('finance.categories.store'), [
            'name' => 'Salary',
            'tx_type' => 'income',
            'icon' => '💰',
        ]);

        $this->post(route('finance.categories.store'), [
            'name' => 'Freelance',
            'tx_type' => 'income',
            'icon' => '💼',
        ]);

        // Create Expense Categories
        $this->post(route('finance.categories.store'), [
            'name' => 'Food',
            'tx_type' => 'expense',
            'icon' => '🍔',
        ]);

        $this->post(route('finance.categories.store'), [
            'name' => 'Transportation',
            'tx_type' => 'expense',
            'icon' => '🚗',
        ]);

        $this->assertEquals(2, FinanceAccount::where('user_id', $this->user->id)->count());
        $this->assertEquals(4, FinanceCategory::where('user_id', $this->user->id)->count());
    }

    private function step4_user_records_transactions(): void
    {
        $this->actingAs($this->user);

        $cashAccount = FinanceAccount::where('user_id', $this->user->id)
            ->where('name', 'Cash Wallet')->first();
        $salaryCategory = FinanceCategory::where('user_id', $this->user->id)
            ->where('name', 'Salary')->first();
        $foodCategory = FinanceCategory::where('user_id', $this->user->id)
            ->where('name', 'Food')->first();

        // Record Income
        $this->post(route('finance.transactions.store'), [
            'account_id' => $cashAccount->id,
            'category_id' => $salaryCategory->id,
            'tx_type' => 'income',
            'amount' => 5000000,
            'note' => 'Monthly Salary',
            'occurred_at' => now(),
        ]);

        // Record Expenses
        $this->post(route('finance.transactions.store'), [
            'account_id' => $cashAccount->id,
            'category_id' => $foodCategory->id,
            'tx_type' => 'expense',
            'amount' => 50000,
            'note' => 'Lunch',
            'occurred_at' => now(),
        ]);

        $this->post(route('finance.transactions.store'), [
            'account_id' => $cashAccount->id,
            'category_id' => $foodCategory->id,
            'tx_type' => 'expense',
            'amount' => 75000,
            'note' => 'Dinner',
            'occurred_at' => now(),
        ]);

        $this->assertGreaterThanOrEqual(3, FinanceTransaction::where('user_id', $this->user->id)->count());
    }

    private function step5_user_manages_schedule(): void
    {
        $this->actingAs($this->user);

        // Create today's schedule
        $this->post(route('schedules.store'), [
            'title' => 'Team Meeting',
            'description' => 'Weekly sync',
            'start_time' => now()->setTime(10, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'is_completed' => false,
        ]);

        // Create tomorrow's schedule
        $this->post(route('schedules.store'), [
            'title' => 'Client Presentation',
            'description' => 'Product demo',
            'start_time' => now()->addDay()->setTime(14, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(16, 0)->format('Y-m-d H:i:s'),
            'location' => 'Client Office',
            'is_completed' => false,
        ]);

        $this->assertEquals(2, Schedule::where('user_id', $this->user->id)->count());
    }

    private function step6_user_creates_notes(): void
    {
        $this->actingAs($this->user);

        // Create meeting notes
        $this->post(route('notes.store'), [
            'title' => 'Meeting Notes - Feb 2026',
            'content' => json_encode([
                [
                    'type' => 'heading',
                    'level' => 1,
                    'content' => 'Team Meeting Notes',
                ],
                [
                    'type' => 'paragraph',
                    'content' => 'Discussed Q1 goals and objectives.',
                ],
                [
                    'type' => 'list',
                    'style' => 'bullet',
                    'items' => [
                        'Increase user engagement by 20%',
                        'Launch new feature by end of month',
                        'Improve response time',
                    ],
                ],
            ]),
            'tags' => ['work', 'meeting'],
        ]);

        // Create personal note
        $this->post(route('notes.store'), [
            'title' => 'Weekend Plans',
            'content' => json_encode([
                [
                    'type' => 'paragraph',
                    'content' => 'Things to do this weekend.',
                ],
            ]),
            'tags' => ['personal'],
        ]);

        $this->assertEquals(2, $this->user->notes()->count());
    }

    private function step7_user_uses_chat_assistant(): void
    {
        $this->actingAs($this->user);

        // Send first message (creates thread)
        $this->post(route('chat.send'), [
            'message' => 'Hello Jarvis, can you help me with my schedule today?',
            'thread_id' => null,
        ]);

        $this->assertGreaterThanOrEqual(1, $this->user->chatThreads()->count());
    }

    private function step8_user_activates_plugins(): void
    {
        $this->actingAs($this->user);

        // Create system plugin if not exists
        $plugin = Plugin::firstOrCreate(
            ['slug' => 'motivational-quotes'],
            [
                'name' => 'Kata Motivasi',
                'description' => 'Kirim quote motivasi harian',
                'version' => '1.0.0',
                'author' => 'ASPRI Team',
                'icon' => '🎯',
                'class_name' => 'App\\Plugins\\MotivationalQuotesPlugin',
                'is_system' => true,
                'config_schema' => [
                    'time' => [
                        'type' => 'time',
                        'label' => 'Waktu Pengiriman',
                        'default' => '09:00',
                    ],
                ],
            ]
        );

        // Activate plugin
        $this->post(route('plugins.activate', $plugin));

        // Configure plugin
        $this->post(route('plugins.config.update', $plugin), [
            'config' => [
                'time' => '08:00',
            ],
        ]);

        // Rate plugin
        $this->post(route('plugins.ratings.store', $plugin), [
            'rating' => 5,
            'comment' => 'Very helpful plugin!',
        ]);

        $userPlugin = $this->user->plugins()->where('plugin_id', $plugin->id)->first();
        $this->assertNotNull($userPlugin);
        $this->assertTrue((bool) $userPlugin->pivot->is_active);
    }

    private function step9_verify_data_integrity(): void
    {
        $this->actingAs($this->user);

        // Verify User Profile
        $this->assertNotNull($this->user->profile);
        $this->assertEquals('Pak', $this->user->profile->call_preference);

        // Verify Finance Data
        $this->assertGreaterThanOrEqual(2, $this->user->financeAccounts()->count());
        $this->assertGreaterThanOrEqual(4, $this->user->financeCategories()->count());
        $this->assertGreaterThanOrEqual(3, $this->user->financeTransactions()->count());

        // Verify Schedule Data
        $this->assertGreaterThanOrEqual(2, $this->user->schedules()->count());

        // Verify Notes Data
        $this->assertGreaterThanOrEqual(2, $this->user->notes()->count());

        // Verify Chat Data
        $this->assertGreaterThanOrEqual(1, $this->user->chatThreads()->count());

        // Verify Plugin Data
        $this->assertGreaterThanOrEqual(1, $this->user->plugins()->count());

        // Verify dashboard shows correct summary
        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('monthlyFinance')
            ->has('todaySchedule')
        );
    }
}
