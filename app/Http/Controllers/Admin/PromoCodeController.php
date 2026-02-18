<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\ActivityLog;
use App\Models\PromoCode;
use App\Services\Subscription\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromoCodeController extends Controller
{
    public function __construct(
        private PromoCodeService $promoCodeService
    ) {}

    public function index(Request $request): Response
    {

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'per_page' => $request->input('per_page', 15),
        ];

        return Inertia::render('admin/promo-codes/Index', [
            'promoCodes' => $this->promoCodeService->getPromoCodes($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse
    {
        $promoCode = $this->promoCodeService->createPromoCode(
            $request->validated(),
            auth()->user()
        );

        ActivityLog::log('create_promo_code', "Created promo code: {$promoCode->code}");

        return back()->with('success', "Kode promo {$promoCode->code} berhasil dibuat.");
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $this->promoCodeService->updatePromoCode($promoCode, $request->validated());

        ActivityLog::log('update_promo_code', "Updated promo code: {$promoCode->code}");

        return back()->with('success', "Kode promo {$promoCode->code} berhasil diperbarui.");
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        $code = $promoCode->code;

        if ($promoCode->usage_count > 0) {
            return back()->with('error', 'Kode promo yang sudah pernah digunakan tidak dapat dihapus.');
        }

        $promoCode->delete();

        ActivityLog::log('delete_promo_code', "Deleted promo code: {$code}");

        return back()->with('success', "Kode promo {$code} berhasil dihapus.");
    }

    public function toggleActive(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        $status = $promoCode->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::log('toggle_promo_code', "Promo code {$promoCode->code} {$status}");

        return back()->with('success', "Kode promo {$promoCode->code} berhasil {$status}.");
    }

    public function show(PromoCode $promoCode): Response
    {
        $promoCode->load('creator');

        $redemptions = $promoCode->redemptions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('admin/promo-codes/Show', [
            'promoCode' => $promoCode,
            'redemptions' => $redemptions,
        ]);
    }
}
