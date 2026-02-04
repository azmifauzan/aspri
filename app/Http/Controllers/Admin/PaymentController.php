<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PaymentProof;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Display payment proofs list.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'per_page' => $request->input('per_page', 15),
        ];

        return Inertia::render('admin/payments/Index', [
            'payments' => $this->subscriptionService->getPaymentProofs($filters),
            'pendingCount' => PaymentProof::pending()->count(),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /**
     * Show single payment proof.
     */
    public function show(PaymentProof $payment): Response
    {
        $payment->load(['user.profile', 'subscription', 'reviewer']);

        return Inertia::render('admin/payments/Show', [
            'payment' => $payment,
            'pricing' => $this->subscriptionService->getPricingInfo(),
        ]);
    }

    /**
     * Approve payment proof.
     */
    public function approve(Request $request, PaymentProof $payment): RedirectResponse
    {
        if (! $payment->isPending()) {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription = $this->subscriptionService->approvePayment(
            $payment,
            auth()->user(),
            $validated['notes'] ?? null
        );

        ActivityLog::log('approve_payment', "Approved payment proof #{$payment->id} for user #{$payment->user_id}");

        return redirect()->route('admin.payments.index')
            ->with('success', "Pembayaran berhasil disetujui. Subscription aktif sampai {$subscription->ends_at->format('d M Y')}.");
    }

    /**
     * Reject payment proof.
     */
    public function reject(Request $request, PaymentProof $payment): RedirectResponse
    {
        if (! $payment->isPending()) {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->subscriptionService->rejectPayment(
            $payment,
            auth()->user(),
            $validated['reason']
        );

        ActivityLog::log('reject_payment', "Rejected payment proof #{$payment->id} for user #{$payment->user_id}");

        return redirect()->route('admin.payments.index')
            ->with('success', 'Pembayaran berhasil ditolak.');
    }
}
