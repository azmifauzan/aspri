<?php

namespace App\Services\Subscription;

use App\Models\PaymentProof;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a free trial subscription for a new user.
     */
    public function createFreeTrial(User $user): Subscription
    {
        $trialDays = (int) SystemSetting::getValue('free_trial_days', 30);

        return Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free_trial',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays($trialDays),
            'price_paid' => 0,
        ]);
    }

    /**
     * Get subscription pricing info.
     *
     * @return array<string, mixed>
     */
    public function getPricingInfo(): array
    {
        return [
            'monthly_price' => (int) SystemSetting::getValue('monthly_price', 10000),
            'yearly_price' => (int) SystemSetting::getValue('yearly_price', 100000),
            'free_trial_days' => (int) SystemSetting::getValue('free_trial_days', 30),
            'free_trial_daily_chat_limit' => (int) SystemSetting::getValue('free_trial_daily_chat_limit', 50),
            'full_member_daily_chat_limit' => (int) SystemSetting::getValue('full_member_daily_chat_limit', 500),
        ];
    }

    /**
     * Get bank transfer info.
     *
     * @return array<string, string>
     */
    public function getBankInfo(): array
    {
        return [
            'bank_name' => SystemSetting::getValue('bank_name', ''),
            'account_number' => SystemSetting::getValue('bank_account_number', ''),
            'account_name' => SystemSetting::getValue('bank_account_name', ''),
        ];
    }

    /**
     * Submit a payment proof.
     */
    public function submitPaymentProof(
        User $user,
        string $planType,
        string $transferProofPath,
        ?string $bankName = null,
        ?string $accountName = null,
        ?\DateTime $transferDate = null
    ): PaymentProof {
        $pricing = $this->getPricingInfo();
        $amount = $planType === 'yearly' ? $pricing['yearly_price'] : $pricing['monthly_price'];

        return PaymentProof::create([
            'user_id' => $user->id,
            'plan_type' => $planType,
            'amount' => $amount,
            'transfer_proof_path' => $transferProofPath,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'transfer_date' => $transferDate,
            'status' => 'pending',
        ]);
    }

    /**
     * Approve a payment proof and activate subscription.
     */
    public function approvePayment(PaymentProof $paymentProof, User $admin, ?string $notes = null): Subscription
    {
        return DB::transaction(function () use ($paymentProof, $admin, $notes) {
            // Update payment proof status
            $paymentProof->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);

            // Calculate subscription end date
            $duration = $paymentProof->plan_type === 'yearly' ? 365 : 30;

            // Check if user has existing active subscription
            $existingSubscription = $paymentProof->user->activeSubscription();
            $startsAt = now();

            // If has active subscription, extend from its end date
            if ($existingSubscription && $existingSubscription->ends_at && $existingSubscription->ends_at->isFuture()) {
                $startsAt = $existingSubscription->ends_at;
                // Mark old subscription as ended
                $existingSubscription->update(['status' => 'expired']);
            }

            // Create new subscription
            $subscription = Subscription::create([
                'user_id' => $paymentProof->user_id,
                'plan' => $paymentProof->plan_type,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays($duration),
                'price_paid' => $paymentProof->amount,
                'payment_method' => 'bank_transfer',
                'notes' => "Payment proof #{$paymentProof->id} approved",
            ]);

            // Link subscription to payment proof
            $paymentProof->update(['subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    /**
     * Reject a payment proof.
     */
    public function rejectPayment(PaymentProof $paymentProof, User $admin, ?string $reason = null): PaymentProof
    {
        $paymentProof->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $reason,
        ]);

        return $paymentProof;
    }

    /**
     * Check and expire old subscriptions.
     */
    public function expireOldSubscriptions(): int
    {
        return Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Get pending payment proofs for admin.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingPayments()
    {
        return PaymentProof::with(['user', 'subscription'])
            ->pending()
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get all payment proofs with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaymentProofs(array $filters = [])
    {
        $query = PaymentProof::with(['user', 'subscription', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
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
