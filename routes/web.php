<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Chat routes
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{thread}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/message', [ChatController::class, 'sendMessage'])->name('chat.send');
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
});

require __DIR__.'/settings.php';
