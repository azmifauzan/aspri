<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemPromoCodeRequest;
use App\Models\PaymentProof;
use App\Services\Subscription\PromoCodeService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Telegram\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private AdminNotificationService $adminNotificationService,
        private PromoCodeService $promoCodeService
    ) {}

    /**
     * Display subscription/upgrade page.
     */
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('subscription/Index', [
            'pricing' => $this->subscriptionService->getPricingInfo(),
            'bankInfo' => $this->subscriptionService->getBankInfo(),
            'subscriptionInfo' => $user->getSubscriptionInfo(),
            'pendingPayments' => $user->paymentProofs()
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get(),
            'paymentHistory' => $user->paymentProofs()
                ->with('subscription')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
            'promoRedemptions' => $user->promoCodeRedemptions()
                ->with('promoCode')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get(),
        ]);
    }

    /**
     * Submit payment proof.
     */
    public function submitPayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_type' => ['required', 'in:monthly,yearly'],
            'transfer_proof' => ['required', 'image', 'max:5120'], // Max 5MB
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'transfer_date' => ['nullable', 'date'],
        ]);

        // Store the transfer proof file
        $path = $request->file('transfer_proof')->store('payment-proofs', 'public');

        $paymentProof = $this->subscriptionService->submitPaymentProof(
            auth()->user(),
            $validated['plan_type'],
            $path,
            $validated['bank_name'] ?? null,
            $validated['account_name'] ?? null,
            $validated['transfer_date'] ? new \DateTime($validated['transfer_date']) : null
        );

        // Notify admins via Telegram
        $this->adminNotificationService->notifyNewPaymentProof($paymentProof);

        return back()->with('success', 'Bukti transfer berhasil dikirim. Kami akan memverifikasi pembayaran Anda dalam 1x24 jam.');
    }

    /**
     * Cancel pending payment proof.
     */
    public function cancelPayment(PaymentProof $paymentProof): RedirectResponse
    {
        // Ensure user owns this payment proof
        if ($paymentProof->user_id !== auth()->id()) {
            abort(403);
        }

        // Can only cancel pending payments
        if (! $paymentProof->isPending()) {
            return back()->with('error', 'Hanya bukti pembayaran pending yang dapat dibatalkan.');
        }

        // Delete the file
        Storage::disk('public')->delete($paymentProof->transfer_proof_path);

        $paymentProof->delete();

        return back()->with('success', 'Bukti pembayaran berhasil dibatalkan.');
    }

    /**
     * Redeem a promo code to extend subscription.
     */
    public function redeemPromoCode(RedeemPromoCodeRequest $request): RedirectResponse
    {
        $result = $this->promoCodeService->redeemPromoCode(
            $request->validated()['code'],
            auth()->user()
        );

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
}
