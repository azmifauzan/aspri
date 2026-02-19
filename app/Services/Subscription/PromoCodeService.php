<?php

namespace App\Services\Subscription;

use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PromoCodeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPromoCode(array $data, User $admin): PromoCode
    {
        return PromoCode::create([
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'duration_days' => $data['duration_days'],
            'max_usages' => $data['max_usages'],
            'expires_at' => $data['expires_at'],
            'created_by' => $admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePromoCode(PromoCode $promoCode, array $data): PromoCode
    {
        $promoCode->update([
            'description' => $data['description'] ?? $promoCode->description,
            'duration_days' => $data['duration_days'],
            'max_usages' => $data['max_usages'],
            'is_active' => $data['is_active'],
            'expires_at' => $data['expires_at'],
        ]);

        return $promoCode;
    }

    /**
     * Validate a promo code for a specific user.
     *
     * @return array{valid: bool, message: string, promo_code?: PromoCode}
     */
    public function validatePromoCode(string $code, User $user): array
    {
        $promoCode = PromoCode::where('code', strtoupper($code))->first();

        if (! $promoCode) {
            return ['valid' => false, 'message' => 'Kode promo tidak ditemukan.'];
        }

        if (! $promoCode->is_active) {
            return ['valid' => false, 'message' => 'Kode promo sudah tidak aktif.'];
        }

        if ($promoCode->isExpired()) {
            return ['valid' => false, 'message' => 'Kode promo sudah kadaluarsa.'];
        }

        if ($promoCode->isFullyRedeemed()) {
            return ['valid' => false, 'message' => 'Kode promo sudah mencapai batas penggunaan.'];
        }

        if ($promoCode->hasBeenRedeemedBy($user)) {
            return ['valid' => false, 'message' => 'Anda sudah pernah menggunakan kode promo ini.'];
        }

        if (! $user->hasActiveSubscription()) {
            return ['valid' => false, 'message' => 'Anda harus memiliki subscription aktif untuk menggunakan kode promo.'];
        }

        return [
            'valid' => true,
            'message' => "Kode promo valid! Anda akan mendapat {$promoCode->duration_days} hari akses full member.",
            'promo_code' => $promoCode,
        ];
    }

    /**
     * Redeem a promo code for a user, extending their subscription.
     *
     * @return array{success: bool, message: string, redemption?: PromoCodeRedemption}
     */
    public function redeemPromoCode(string $code, User $user): array
    {
        $validation = $this->validatePromoCode($code, $user);

        if (! $validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        /** @var PromoCode $promoCode */
        $promoCode = $validation['promo_code'];

        return DB::transaction(function () use ($promoCode, $user) {
            $subscription = $user->activeSubscription();

            // If no active subscription yet, create one as monthly plan starting now
            if (! $subscription) {
                $newEndsAt = now()->addDays($promoCode->duration_days);

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan' => 'monthly',
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $newEndsAt,
                    'price_paid' => 0,
                    'notes' => 'Activated via promo code: '.$promoCode->code,
                ]);

                $previousEndsAt = now();
            } elseif ($subscription->isFreeTrial()) {
                // Upgrade free trial to full member (monthly plan) for duration_days from now
                $previousEndsAt = $subscription->ends_at->copy();
                $newEndsAt = now()->addDays($promoCode->duration_days);

                $subscription->update([
                    'plan' => 'monthly',
                    'ends_at' => $newEndsAt,
                    'notes' => 'Upgraded from free trial via promo code: '.$promoCode->code,
                ]);
            } else {
                // Already paid member — extend ends_at
                $previousEndsAt = $subscription->ends_at->copy();
                $newEndsAt = $previousEndsAt->copy()->addDays($promoCode->duration_days);

                $subscription->update([
                    'ends_at' => $newEndsAt,
                ]);
            }

            $redemption = PromoCodeRedemption::create([
                'promo_code_id' => $promoCode->id,
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'days_added' => $promoCode->duration_days,
                'previous_ends_at' => $previousEndsAt,
                'new_ends_at' => $newEndsAt,
            ]);

            $promoCode->increment('usage_count');

            return [
                'success' => true,
                'message' => "Kode promo berhasil digunakan! Subscription diperpanjang {$promoCode->duration_days} hari hingga {$newEndsAt->format('d M Y')}.",
                'redemption' => $redemption,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPromoCodes(array $filters = []): array
    {
        $query = PromoCode::with('creator')
            ->withCount('redemptions')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'active' => $query->valid(),
                'expired' => $query->expired(),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        $paginator = $query->paginate($filters['per_page'] ?? 15);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
