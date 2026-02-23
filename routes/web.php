<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\QueueMonitorController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Plugin;
use App\Services\Admin\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (SettingsService $settingsService) {
    // Get 6 random plugins for landing page
    $featuredPlugins = Plugin::where('is_system', true)
        ->inRandomOrder()
        ->take(6)
        ->get(['slug', 'name', 'description', 'icon']);

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'pricing' => $settingsService->getSubscriptionSettings(),
        'featuredPlugins' => $featuredPlugins,
    ]);
})->name('home');

// Public plugin explorer (no auth required)
Route::get('/explore-plugins', function (Request $request) {
    $query = Plugin::where('is_system', true)
        ->withCount('ratings')
        ->withAvg('ratings', 'rating');

    // Search filter
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
        });
    }

    // Sorting - default by rating
    $sortBy = $request->input('sort_by', 'rating');
    if ($sortBy === 'rating') {
        $query->orderByDesc('ratings_avg_rating')
            ->orderByDesc('ratings_count');
    } elseif ($sortBy === 'name') {
        $query->orderBy('name');
    }

    $plugins = $query->get()
        ->when($request->input('min_rating'), function ($collection, $minRating) {
            return $collection->filter(fn ($plugin) => ($plugin->ratings_avg_rating ?? 0) >= $minRating);
        })
        ->map(fn ($plugin) => [
            'slug' => $plugin->slug,
            'name' => $plugin->name,
            'description' => $plugin->description,
            'icon' => $plugin->icon,
            'average_rating' => round($plugin->ratings_avg_rating ?? 0, 1),
            'total_ratings' => $plugin->ratings_count ?? 0,
        ]);

    // Manual pagination since we filtered after query
    $page = $request->input('page', 1);
    $perPage = 12;
    $paginatedPlugins = new \Illuminate\Pagination\LengthAwarePaginator(
        $plugins->forPage($page, $perPage),
        $plugins->count(),
        $perPage,
        $page,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    return Inertia::render('ExplorePlugins', [
        'plugins' => $paginatedPlugins,
        'filters' => $request->only(['search', 'min_rating', 'sort_by']),
    ]);
})->name('explore-plugins');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Locale preference
    Route::post('locale', function (Request $request) {
        $request->validate(['locale' => 'required|in:en,id']);
        $request->user()->profile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['locale' => $request->input('locale')]
        );

        return response()->json(['status' => 'ok']);
    })->name('locale.update');

    // Subscription routes
    Route::get('subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('subscription/payment', [SubscriptionController::class, 'submitPayment'])->name('subscription.submit-payment');
    Route::delete('subscription/payment/{paymentProof}', [SubscriptionController::class, 'cancelPayment'])->name('subscription.cancel-payment');
    Route::post('subscription/redeem-promo', [SubscriptionController::class, 'redeemPromoCode'])->name('subscription.redeem-promo');

    // Chat routes
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{thread}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/message', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('chat/message/stream', [ChatController::class, 'sendMessageStream'])->name('chat.send.stream');
    Route::delete('chat/{thread}', [ChatController::class, 'destroy'])->name('chat.destroy');

    // Finance routes
    Route::get('finance', [FinanceController::class, 'index'])->name('finance');
    Route::get('finance/transactions', [FinanceController::class, 'transactions'])->name('finance.transactions');
    Route::post('finance/transactions', [FinanceController::class, 'storeTransaction'])->name('finance.transactions.store');
    Route::put('finance/transactions/{transaction}', [FinanceController::class, 'updateTransaction'])->name('finance.transactions.update');
    Route::delete('finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->name('finance.transactions.destroy');
    Route::get('finance/categories', [FinanceController::class, 'categories'])->name('finance.categories');
    Route::post('finance/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store');
    Route::put('finance/categories/{category}', [FinanceController::class, 'updateCategory'])->name('finance.categories.update');
    Route::delete('finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->name('finance.categories.destroy');
    Route::get('finance/accounts', [FinanceController::class, 'accounts'])->name('finance.accounts');
    Route::post('finance/accounts', [FinanceController::class, 'storeAccount'])->name('finance.accounts.store');

    // Schedule routes
    Route::resource('schedules', \App\Http\Controllers\ScheduleController::class)->only(['index', 'store', 'update', 'destroy']);

    // Note routes
    Route::resource('notes', \App\Http\Controllers\NoteController::class)->only(['index', 'store', 'update', 'destroy']);

    // Plugin routes
    Route::get('plugins', [\App\Http\Controllers\PluginController::class, 'index'])->name('plugins.index');
    Route::get('plugins/{plugin}', [\App\Http\Controllers\PluginController::class, 'show'])->name('plugins.show');
    Route::post('plugins/{plugin}/activate', [\App\Http\Controllers\PluginController::class, 'activate'])->name('plugins.activate');
    Route::post('plugins/{plugin}/deactivate', [\App\Http\Controllers\PluginController::class, 'deactivate'])->name('plugins.deactivate');
    Route::post('plugins/{plugin}/config', [\App\Http\Controllers\PluginController::class, 'updateConfig'])->name('plugins.config.update');
    Route::delete('plugins/{plugin}/config', [\App\Http\Controllers\PluginController::class, 'resetConfig'])->name('plugins.config.reset');
    Route::post('plugins/{plugin}/schedule', [\App\Http\Controllers\PluginController::class, 'updateSchedule'])->name('plugins.schedule.update');
    Route::delete('plugins/{plugin}/schedule/{scheduleId}', [\App\Http\Controllers\PluginController::class, 'deleteSchedule'])->name('plugins.schedule.delete');
    Route::post('plugins/{plugin}/test', [\App\Http\Controllers\PluginController::class, 'testExecute'])->name('plugins.test');

    // Plugin Rating routes
    Route::post('plugins/{plugin}/ratings', [\App\Http\Controllers\PluginRatingController::class, 'store'])->name('plugins.ratings.store');
    Route::put('plugins/{plugin}/ratings/{rating}', [\App\Http\Controllers\PluginRatingController::class, 'update'])->name('plugins.ratings.update');
    Route::delete('plugins/{plugin}/ratings/{rating}', [\App\Http\Controllers\PluginRatingController::class, 'destroy'])->name('plugins.ratings.destroy');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('index');
    Route::get('/monitoring', [AdminDashboardController::class, 'monitoringData'])->name('monitoring');

    // User Management
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    // Settings (Super Admin only)
    Route::middleware('super_admin')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.update-ai');
        Route::post('/settings/telegram', [SettingsController::class, 'updateTelegram'])->name('settings.update-telegram');
        Route::post('/settings/app', [SettingsController::class, 'updateApp'])->name('settings.update-app');
        Route::post('/settings/subscription', [SettingsController::class, 'updateSubscription'])->name('settings.update-subscription');
        Route::post('/settings/email', [SettingsController::class, 'updateEmail'])->name('settings.update-email');
        Route::post('/settings/test-ai', [SettingsController::class, 'testAi'])->name('settings.test-ai');
        Route::post('/settings/test-telegram', [SettingsController::class, 'testTelegram'])->name('settings.test-telegram');
        Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
    });

    // Payment Management
    Route::get('/promo-codes', [PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::post('/promo-codes', [PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::get('/promo-codes/{promoCode}', [PromoCodeController::class, 'show'])->name('promo-codes.show');
    Route::put('/promo-codes/{promoCode}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
    Route::delete('/promo-codes/{promoCode}', [PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');
    Route::post('/promo-codes/{promoCode}/toggle-active', [PromoCodeController::class, 'toggleActive'])->name('promo-codes.toggle-active');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

    // Activity Logs
    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('/activity/{activityLog}', [ActivityLogController::class, 'show'])->name('activity.show');

    // Queue Monitoring
    Route::get('/queues', [QueueMonitorController::class, 'index'])->name('queues.index');
    Route::get('/queues/data', [QueueMonitorController::class, 'data'])->name('queues.data');
    Route::post('/queues/{id}/retry', [QueueMonitorController::class, 'retryJob'])->name('queues.retry');
    Route::post('/queues/retry-all', [QueueMonitorController::class, 'retryAll'])->name('queues.retry-all');
    Route::delete('/queues/{id}', [QueueMonitorController::class, 'deleteJob'])->name('queues.delete');
    Route::post('/queues/flush', [QueueMonitorController::class, 'flushFailed'])->name('queues.flush');
    Route::post('/queues/clear', [QueueMonitorController::class, 'clearPending'])->name('queues.clear');
});

require __DIR__.'/settings.php';
