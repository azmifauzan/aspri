<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected PromoCodeService $promoCodeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promoCodeService = app(PromoCodeService::class);
    }

    // =============================================
    // PromoCode Model Tests
    // =============================================

    public function test_promo_code_is_valid_when_active_not_expired_and_has_usages(): void
    {
        $promoCode = PromoCode::factory()->create([
            'is_active' => true,
            'expires_at' => now()->addDays(10),
            'max_usages' => 100,
            'usage_count' => 0,
        ]);

        $this->assertTrue($promoCode->isValid());
    }

    public function test_promo_code_is_not_valid_when_inactive(): void
    {
        $promoCode = PromoCode::factory()->inactive()->create();

        $this->assertFalse($promoCode->isValid());
    }

    public function test_promo_code_is_not_valid_when_expired(): void
    {
        $promoCode = PromoCode::factory()->expired()->create();

        $this->assertFalse($promoCode->isValid());
    }

    public function test_promo_code_is_not_valid_when_fully_redeemed(): void
    {
        $promoCode = PromoCode::factory()->fullyRedeemed()->create();

        $this->assertFalse($promoCode->isValid());
    }

    public function test_promo_code_remaining_usages_attribute(): void
    {
        $promoCode = PromoCode::factory()->create([
            'max_usages' => 50,
            'usage_count' => 20,
        ]);

        $this->assertEquals(30, $promoCode->remaining_usages);
    }

    public function test_promo_code_has_been_redeemed_by_user(): void
    {
        $promoCode = PromoCode::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($promoCode->hasBeenRedeemedBy($user));

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'days_added' => $promoCode->duration_days,
        ]);

        $this->assertTrue($promoCode->hasBeenRedeemedBy($user));
    }

    // =============================================
    // PromoCodeService Validation Tests
    // =============================================

    public function test_validate_returns_invalid_for_nonexistent_code(): void
    {
        $user = User::factory()->create();

        $result = $this->promoCodeService->validatePromoCode('NONEXISTENT', $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tidak ditemukan', $result['message']);
    }

    public function test_validate_returns_invalid_for_inactive_code(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->inactive()->create();

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tidak aktif', $result['message']);
    }

    public function test_validate_returns_invalid_for_expired_code(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->expired()->create();

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('kadaluarsa', $result['message']);
    }

    public function test_validate_returns_invalid_for_fully_redeemed_code(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->fullyRedeemed()->create();

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('batas penggunaan', $result['message']);
    }

    public function test_validate_returns_invalid_when_user_already_redeemed(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create();

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'days_added' => $promoCode->duration_days,
        ]);

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('sudah pernah', $result['message']);
    }

    public function test_validate_returns_invalid_when_no_active_subscription(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('subscription aktif', $result['message']);
    }

    public function test_validate_returns_valid_for_good_code(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create();

        $result = $this->promoCodeService->validatePromoCode($promoCode->code, $user);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('promo_code', $result);
    }

    public function test_validate_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create(['code' => 'ASPRI-TEST']);

        $result = $this->promoCodeService->validatePromoCode('aspri-test', $user);

        $this->assertTrue($result['valid']);
    }

    // =============================================
    // PromoCodeService Redemption Tests
    // =============================================

    public function test_redeem_extends_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDays(10),
        ]);
        $promoCode = PromoCode::factory()->create([
            'duration_days' => 30,
        ]);

        $originalEndsAt = $subscription->ends_at->copy();

        $result = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);

        $this->assertTrue($result['success']);

        $subscription->refresh();
        $this->assertEquals(
            $originalEndsAt->addDays(30)->format('Y-m-d'),
            $subscription->ends_at->format('Y-m-d')
        );
    }

    public function test_redeem_creates_redemption_record(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDays(10),
        ]);
        $promoCode = PromoCode::factory()->create(['duration_days' => 15]);

        $result = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'days_added' => 15,
        ]);
    }

    public function test_redeem_increments_usage_count(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create(['usage_count' => 5]);

        $this->promoCodeService->redeemPromoCode($promoCode->code, $user);

        $promoCode->refresh();
        $this->assertEquals(6, $promoCode->usage_count);
    }

    public function test_redeem_fails_for_invalid_code(): void
    {
        $user = User::factory()->create();

        $result = $this->promoCodeService->redeemPromoCode('INVALID', $user);

        $this->assertFalse($result['success']);
    }

    public function test_user_cannot_redeem_same_code_twice(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create();

        $result1 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);
        $this->assertTrue($result1['success']);

        $result2 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('sudah pernah', $result2['message']);
    }

    public function test_single_use_code_cannot_be_used_by_second_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user1->id]);
        Subscription::factory()->create(['user_id' => $user2->id]);
        $promoCode = PromoCode::factory()->singleUse()->create();

        $result1 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user1);
        $this->assertTrue($result1['success']);

        $result2 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user2);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('batas penggunaan', $result2['message']);
    }

    // =============================================
    // Admin PromoCode Service Tests
    // =============================================

    public function test_create_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $promoCode = $this->promoCodeService->createPromoCode([
            'code' => 'test-promo',
            'description' => 'Test promo code',
            'duration_days' => 30,
            'max_usages' => 100,
            'expires_at' => now()->addDays(30)->toDateString(),
        ], $admin);

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'TEST-PROMO',
            'duration_days' => 30,
            'max_usages' => 100,
            'created_by' => $admin->id,
        ]);
    }

    public function test_update_promo_code(): void
    {
        $promoCode = PromoCode::factory()->create();

        $this->promoCodeService->updatePromoCode($promoCode, [
            'description' => 'Updated description',
            'duration_days' => 60,
            'max_usages' => 200,
            'is_active' => false,
            'expires_at' => now()->addDays(60)->toDateString(),
        ]);

        $promoCode->refresh();
        $this->assertEquals('Updated description', $promoCode->description);
        $this->assertEquals(60, $promoCode->duration_days);
        $this->assertEquals(200, $promoCode->max_usages);
        $this->assertFalse($promoCode->is_active);
    }

    // =============================================
    // HTTP Integration Tests - Admin
    // =============================================

    public function test_admin_can_view_promo_codes_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        PromoCode::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/promo-codes');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Index')
            ->has('promoCodes.data', 3)
        );
    }

    public function test_regular_user_cannot_access_admin_promo_codes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/promo-codes');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post('/admin/promo-codes', [
            'code' => 'NEW-PROMO',
            'description' => 'New promo code',
            'duration_days' => 30,
            'max_usages' => 50,
            'expires_at' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'NEW-PROMO',
            'duration_days' => 30,
            'max_usages' => 50,
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_create_duplicate_code(): void
    {
        $admin = User::factory()->superAdmin()->create();
        PromoCode::factory()->create(['code' => 'DUPLICATE']);

        $response = $this->actingAs($admin)->post('/admin/promo-codes', [
            'code' => 'DUPLICATE',
            'duration_days' => 30,
            'max_usages' => 50,
            'expires_at' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create();

        $response = $this->actingAs($admin)->put("/admin/promo-codes/{$promoCode->id}", [
            'description' => 'Updated',
            'duration_days' => 60,
            'max_usages' => 200,
            'is_active' => true,
            'expires_at' => now()->addDays(60)->toDateString(),
        ]);

        $response->assertRedirect();

        $promoCode->refresh();
        $this->assertEquals('Updated', $promoCode->description);
        $this->assertEquals(60, $promoCode->duration_days);
    }

    public function test_admin_can_delete_unused_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create(['usage_count' => 0]);

        $response = $this->actingAs($admin)->delete("/admin/promo-codes/{$promoCode->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('promo_codes', ['id' => $promoCode->id]);
    }

    public function test_admin_cannot_delete_used_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create(['usage_count' => 1]);

        $response = $this->actingAs($admin)->delete("/admin/promo-codes/{$promoCode->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('promo_codes', ['id' => $promoCode->id]);
    }

    public function test_admin_can_toggle_promo_code_active(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->post("/admin/promo-codes/{$promoCode->id}/toggle-active");

        $response->assertRedirect();

        $promoCode->refresh();
        $this->assertFalse($promoCode->is_active);
    }

    // =============================================
    // HTTP Integration Tests - User
    // =============================================

    public function test_user_can_view_subscription_page_with_promo_section(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->freeTrial()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('subscription/Index')
            ->has('promoRedemptions')
        );
    }

    public function test_user_can_redeem_valid_promo_code(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDays(10),
        ]);
        $promoCode = PromoCode::factory()->create(['duration_days' => 30]);

        $response = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $subscription->refresh();
        $this->assertEquals(
            now()->addDays(40)->format('Y-m-d'),
            $subscription->ends_at->format('Y-m-d')
        );

        $this->assertDatabaseHas('promo_code_redemptions', [
            'user_id' => $user->id,
            'promo_code_id' => $promoCode->id,
            'days_added' => 30,
        ]);
    }

    public function test_user_cannot_redeem_invalid_code(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => 'INVALID-CODE',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_cannot_redeem_expired_code(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->expired()->create();

        $response = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_cannot_redeem_without_subscription(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();

        $response = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_cannot_redeem_same_code_twice_via_http(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create();

        $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_guest_cannot_redeem_promo_code(): void
    {
        $promoCode = PromoCode::factory()->create();

        $response = $this->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);

        $response->assertRedirect('/login');
    }

    // =============================================
    // Admin Filter Tests
    // =============================================

    public function test_admin_can_filter_promo_codes_by_status(): void
    {
        $admin = User::factory()->superAdmin()->create();
        PromoCode::factory()->create(['is_active' => true, 'expires_at' => now()->addDays(10)]);
        PromoCode::factory()->expired()->create();
        PromoCode::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get('/admin/promo-codes?status=active');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Index')
            ->has('promoCodes.data', 1)
        );
    }

    public function test_admin_can_search_promo_codes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        PromoCode::factory()->create(['code' => 'SEARCH-ME', 'description' => 'Find this one']);
        PromoCode::factory()->create(['code' => 'HIDDEN', 'description' => 'Not this']);

        $response = $this->actingAs($admin)->get('/admin/promo-codes?search=SEARCH');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Index')
            ->has('promoCodes.data', 1)
        );
    }

    // =============================================
    // Admin Show/Detail Page Tests
    // =============================================

    public function test_admin_can_view_promo_code_detail_page(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Show')
            ->has('promoCode')
            ->has('redemptions')
            ->where('promoCode.id', $promoCode->id)
            ->where('promoCode.code', $promoCode->code)
        );
    }

    public function test_admin_can_see_who_redeemed_promo_code(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create(['duration_days' => 30]);

        $user1 = User::factory()->create(['name' => 'User Satu']);
        $user2 = User::factory()->create(['name' => 'User Dua']);

        $sub1 = Subscription::factory()->create(['user_id' => $user1->id]);
        $sub2 = Subscription::factory()->create(['user_id' => $user2->id]);

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user1->id,
            'subscription_id' => $sub1->id,
            'days_added' => 30,
            'previous_ends_at' => now(),
            'new_ends_at' => now()->addDays(30),
        ]);

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user2->id,
            'subscription_id' => $sub2->id,
            'days_added' => 30,
            'previous_ends_at' => now(),
            'new_ends_at' => now()->addDays(30),
        ]);

        $promoCode->update(['usage_count' => 2]);

        $response = $this->actingAs($admin)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Show')
            ->has('redemptions.data', 2)
            ->has('redemptions.data.0.user.name')
            ->has('redemptions.data.0.user.email')
            ->has('redemptions.data.0.days_added')
            ->has('redemptions.data.0.created_at')
            ->has('redemptions.data.1.user.name')
        );

        // Verify both users exist in redemptions
        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id' => $promoCode->id,
            'user_id' => $user1->id,
        ]);
        $this->assertDatabaseHas('promo_code_redemptions', [
            'promo_code_id' => $promoCode->id,
            'user_id' => $user2->id,
        ]);
    }

    public function test_admin_show_page_includes_redemption_timestamps(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create(['duration_days' => 15]);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $previousEndsAt = now()->addDays(5);
        $newEndsAt = now()->addDays(20);

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'days_added' => 15,
            'previous_ends_at' => $previousEndsAt,
            'new_ends_at' => $newEndsAt,
        ]);

        $response = $this->actingAs($admin)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Show')
            ->has('redemptions.data', 1)
            ->where('redemptions.data.0.days_added', 15)
            ->has('redemptions.data.0.previous_ends_at')
            ->has('redemptions.data.0.new_ends_at')
            ->has('redemptions.data.0.created_at')
        );
    }

    public function test_admin_show_page_empty_redemptions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $promoCode = PromoCode::factory()->create();

        $response = $this->actingAs($admin)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Show')
            ->has('redemptions.data', 0)
        );
    }

    public function test_regular_user_cannot_access_promo_code_detail(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();

        $response = $this->actingAs($user)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(403);
    }

    public function test_admin_show_page_loads_creator_info(): void
    {
        $admin = User::factory()->superAdmin()->create(['name' => 'Admin Creator']);
        $promoCode = PromoCode::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get("/admin/promo-codes/{$promoCode->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/promo-codes/Show')
            ->where('promoCode.creator.name', 'Admin Creator')
        );
    }

    // =============================================
    // User One-Time Redemption Enforcement Tests
    // =============================================

    public function test_user_cannot_redeem_any_promo_code_more_than_once(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'ends_at' => now()->addDays(10),
        ]);
        $promoCode = PromoCode::factory()->create(['max_usages' => 100]);

        // First redemption succeeds
        $result = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);
        $this->assertTrue($result['success']);

        // Second redemption fails
        $result = $this->promoCodeService->redeemPromoCode($promoCode->code, $user);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('sudah pernah', $result['message']);

        // Only one redemption in database
        $this->assertEquals(1, PromoCodeRedemption::where('user_id', $user->id)->where('promo_code_id', $promoCode->id)->count());
    }

    public function test_different_users_can_redeem_same_code(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Subscription::factory()->create(['user_id' => $user1->id]);
        Subscription::factory()->create(['user_id' => $user2->id]);
        Subscription::factory()->create(['user_id' => $user3->id]);

        $promoCode = PromoCode::factory()->create(['max_usages' => 100]);

        $r1 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user1);
        $r2 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user2);
        $r3 = $this->promoCodeService->redeemPromoCode($promoCode->code, $user3);

        $this->assertTrue($r1['success']);
        $this->assertTrue($r2['success']);
        $this->assertTrue($r3['success']);

        $promoCode->refresh();
        $this->assertEquals(3, $promoCode->usage_count);
    }

    public function test_user_cannot_redeem_same_code_twice_via_http_returns_error(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);
        $promoCode = PromoCode::factory()->create(['max_usages' => 100]);

        // First request succeeds
        $response1 = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);
        $response1->assertRedirect();
        $response1->assertSessionHas('success');

        // Second request fails with error
        $response2 = $this->actingAs($user)->post('/subscription/redeem-promo', [
            'code' => $promoCode->code,
        ]);
        $response2->assertRedirect();
        $response2->assertSessionHas('error');

        // Only 1 redemption record
        $this->assertEquals(1, PromoCodeRedemption::where('user_id', $user->id)->count());
    }

    public function test_database_unique_constraint_prevents_duplicate_redemption(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        // Create first redemption
        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'days_added' => $promoCode->duration_days,
            'previous_ends_at' => now(),
            'new_ends_at' => now()->addDays($promoCode->duration_days),
        ]);

        // Attempt duplicate - should throw exception due to unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        PromoCodeRedemption::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'days_added' => $promoCode->duration_days,
            'previous_ends_at' => now(),
            'new_ends_at' => now()->addDays($promoCode->duration_days),
        ]);
    }

    // =============================================
    // Promo Code Scope Tests
    // =============================================

    public function test_valid_scope_returns_only_valid_codes(): void
    {
        PromoCode::factory()->create(['is_active' => true, 'expires_at' => now()->addDays(10)]);
        PromoCode::factory()->expired()->create();
        PromoCode::factory()->inactive()->create();
        PromoCode::factory()->fullyRedeemed()->create();

        $validCodes = PromoCode::valid()->get();

        $this->assertCount(1, $validCodes);
    }

    public function test_expired_scope_returns_only_expired_codes(): void
    {
        PromoCode::factory()->create(['expires_at' => now()->addDays(10)]);
        PromoCode::factory()->expired()->create();

        $expiredCodes = PromoCode::expired()->get();

        $this->assertCount(1, $expiredCodes);
    }
}
