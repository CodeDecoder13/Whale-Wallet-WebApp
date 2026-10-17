<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PodController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::resource('cards', CardController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('pods', PodController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::post('transactions/quick', [TransactionController::class, 'quick'])->name('transactions.quick');
    Route::post('transactions/transfer', [TransactionController::class, 'transfer'])->name('transactions.transfer');
    Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('transactions/export.md', [TransactionController::class, 'exportMarkdown'])->name('transactions.export-md');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('forecast', ForecastController::class)->name('forecast');
    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('tips', [RecommendationController::class, 'index'])->name('tips.index');
    Route::post('tips', [RecommendationController::class, 'store'])->middleware('throttle:6,1')->name('tips.store');
    Route::post('tips/{recommendation}/dismiss', [RecommendationController::class, 'dismiss'])->name('tips.dismiss');
    Route::post('consent/ai', [RecommendationController::class, 'consent'])->name('consent.ai');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
