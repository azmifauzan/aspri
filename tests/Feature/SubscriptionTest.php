<?php

namespace Tests\Feature;

use App\Models\PaymentProof;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = app(SubscriptionService::class);
    }

    public function test_create_free_trial_subscription(): void
    {
        $user = User::factory()->create();

        $subscription = $this->subscriptionService->createFreeTrial($user);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('free_trial', $subscription->plan);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals(0, $subscription->price_paid);
        $this->assertTrue($subscription->ends_at->isFuture());
    }

    public function test_free_trial_respects_system_setting(): void
    {
        SystemSetting::setValue('free_trial_days', 14);
        $user = User::factory()->create();

        $subscription = $this->subscriptionService->createFreeTrial($user);

        $expectedEndDate = now()->addDays(14);
        $this->assertEquals(
            $expectedEndDate->format('Y-m-d'),
            $subscription->ends_at->format('Y-m-d')
        );
    }

    public function test_subscription_is_active(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->addDays(10),
        ]);

        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_is_not_active_when_expired(): void
    {
        $subscription = Subscription::factory()->expired()->create();

        $this->assertFalse($subscription->isActive());
    }

    public function test_subscription_days_remaining_attribute(): void
    {
        $subscription = Subscription::factory()->create([
            'ends_at' => now()->addDays(15),
        ]);

        // diffInDays may round down, so we check it's approximately correct
        $daysRemaining = $subscription->days_remaining;
        $this->assertTrue($daysRemaining >= 14 && $daysRemaining <= 15);
    }

    public function test_user_can_view_subscription_page(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->freeTrial()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('subscription/Index')
            ->has('subscriptionInfo')
            ->has('pricing')
            ->has('bankInfo')
        );
    }

    public function test_user_can_submit_payment_proof(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Subscription::factory()->freeTrial()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->image('payment-proof.jpg');

        $response = $this->actingAs($user)->post('/subscription/payment', [
            'plan_type' => 'monthly',
            'transfer_proof' => $file,
            'bank_name' => 'BCA',
            'account_name' => 'John Doe',
            'transfer_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('payment_proofs', [
            'user_id' => $user->id,
            'plan_type' => 'monthly',
            'amount' => 10000,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_payment(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        Subscription::factory()->freeTrial()->create(['user_id' => $user->id]);

        $paymentProof = PaymentProof::factory()->monthly()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payments/{$paymentProof->id}/approve", [
            'notes' => 'Payment verified',
        ]);

        $response->assertRedirect();

        $paymentProof->refresh();
        $this->assertEquals('approved', $paymentProof->status);
        $this->assertEquals($admin->id, $paymentProof->reviewed_by);
        $this->assertNotNull($paymentProof->reviewed_at);

        // Check new subscription created
        $newSubscription = Subscription::where('user_id', $user->id)
            ->where('plan', 'monthly')
            ->first();

        $this->assertNotNull($newSubscription);
        $this->assertEquals('active', $newSubscription->status);
        $this->assertEquals(10000, $newSubscription->price_paid);
    }

    public function test_admin_can_reject_payment(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $paymentProof = PaymentProof::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payments/{$paymentProof->id}/reject", [
            'reason' => 'Invalid payment proof - amount does not match',
        ]);

        $response->assertRedirect();

        $paymentProof->refresh();
        $this->assertEquals('rejected', $paymentProof->status);
        $this->assertEquals('Invalid payment proof - amount does not match', $paymentProof->admin_notes);
    }

    public function test_regular_user_cannot_access_admin_payments(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/payments');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_payments_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        PaymentProof::factory()->count(5)->create();

        $response = $this->actingAs($admin)->get('/admin/payments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/payments/Index')
            ->has('payments.data', 5)
        );
    }

    public function test_approved_payment_extends_subscription(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        // Create existing monthly subscription
        Subscription::factory()->monthly()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDays(15),
        ]);

        // Submit new payment proof for yearly
        $paymentProof = PaymentProof::factory()->yearly()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($admin)->post("/admin/payments/{$paymentProof->id}/approve");

        $newSubscription = Subscription::where('user_id', $user->id)
            ->where('plan', 'yearly')
            ->first();

        $this->assertNotNull($newSubscription);
        // New subscription should start from the end of the existing one
        $this->assertEquals(
            now()->addDays(15)->format('Y-m-d'),
            $newSubscription->starts_at->format('Y-m-d')
        );
    }

    public function test_get_pricing_info(): void
    {
        SystemSetting::setValue('monthly_price', 15000);
        SystemSetting::setValue('yearly_price', 150000);
        SystemSetting::setValue('free_trial_days', 14);

        $pricing = $this->subscriptionService->getPricingInfo();

        $this->assertEquals(15000, $pricing['monthly_price']);
        $this->assertEquals(150000, $pricing['yearly_price']);
        $this->assertEquals(14, $pricing['free_trial_days']);
    }

    public function test_expire_old_subscriptions(): void
    {
        // Create an expired subscription
        Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->subDays(5),
        ]);

        // Create an active subscription
        Subscription::factory()->create([
            'status' => 'active',
            'ends_at' => now()->addDays(10),
        ]);

        $expiredCount = $this->subscriptionService->expireOldSubscriptions();

        $this->assertEquals(1, $expiredCount);

        $this->assertEquals(1, Subscription::where('status', 'expired')->count());
        $this->assertEquals(1, Subscription::where('status', 'active')->count());
    }

    public function test_payment_proof_has_transfer_proof_url(): void
    {
        Storage::fake('public');

        $paymentProof = PaymentProof::factory()->create([
            'transfer_proof_path' => 'payment-proofs/test-proof.jpg',
        ]);

        $this->assertNotNull($paymentProof->transfer_proof_url);
        $this->assertStringContainsString('payment-proofs/test-proof.jpg', $paymentProof->transfer_proof_url);
    }
}
