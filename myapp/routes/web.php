<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PodController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// public site: one entry per page, so the sitemap and the footer stay in step with the routes
Route::inertia('/about', 'About')->name('about');
Route::inertia('/pricing', 'Pricing')->name('pricing');
Route::inertia('/docs', 'Docs')->name('docs');
Route::inertia('/privacy', 'privacy/Overview')->name('privacy');
Route::inertia('/privacy/ai', 'privacy/Ai')->name('privacy.ai');
Route::inertia('/privacy/security', 'privacy/Security')->name('privacy.security');
Route::inertia('/privacy/processors', 'privacy/Processors')->name('privacy.processors');
Route::inertia('/privacy/changes', 'privacy/Changes')->name('privacy.changes');
Route::inertia('/privacy-policy', 'legal/Privacy')->name('privacy.policy');
Route::inertia('/terms', 'legal/Terms')->name('terms');
Route::inertia('/cookies', 'legal/Cookies')->name('cookies');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');

Route::get('/.well-known/security.txt', fn () => response(
    "Contact: mailto:support@whalewallet.cloud\nExpires: ".now()->addYear()->toIso8601ZuluString()."\nPreferred-Languages: en, fil\nCanonical: ".url('/.well-known/security.txt')."\n",
    200, ['Content-Type' => 'text/plain; charset=utf-8'],
));

Route::get('/sitemap.xml', fn () => response(
    '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
    .collect(['home', 'about', 'pricing', 'docs', 'privacy', 'privacy.ai', 'privacy.security', 'privacy.processors', 'privacy.changes', 'privacy.policy', 'terms', 'cookies', 'contact'])
        ->map(fn ($name) => '<url><loc>'.route($name).'</loc></url>')->implode('')
    .'</urlset>',
    200, ['Content-Type' => 'application/xml'],
));

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

    Route::post('income', [IncomeController::class, 'store'])->name('income.store');
    Route::delete('income/{income}', [IncomeController::class, 'destroy'])->name('income.destroy');

    Route::get('forecast', ForecastController::class)->name('forecast');
    Route::resource('subscriptions', SubscriptionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('pods/{pod}/fund', [PodController::class, 'fund'])->name('pods.fund');
    Route::post('subscriptions/{subscription}/pay', [SubscriptionController::class, 'pay'])->name('subscriptions.pay');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');

    Route::get('tips', [RecommendationController::class, 'index'])->name('tips.index');
    Route::post('tips', [RecommendationController::class, 'store'])->middleware('throttle:6,1')->name('tips.store');
    Route::post('tips/{recommendation}/dismiss', [RecommendationController::class, 'dismiss'])->name('tips.dismiss');
    Route::post('consent/ai', [RecommendationController::class, 'consent'])->name('consent.ai');

    Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::post('receipts', [ReceiptController::class, 'store'])->middleware(['ai.consent', 'throttle:10,1'])->name('receipts.store');
    Route::post('receipts/keep', [ReceiptController::class, 'keep'])->name('receipts.keep');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm'])->name('receipts.confirm');
    Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/recent', [ChatController::class, 'recent'])->name('chat.recent');
    Route::post('chat/threads', [ChatController::class, 'newThread'])->name('chat.threads.store');
    Route::patch('chat/threads/{thread}', [ChatController::class, 'renameThread'])->name('chat.threads.update');
    Route::delete('chat/threads/{thread}', [ChatController::class, 'deleteThread'])->name('chat.threads.destroy');
    Route::post('chat/messages', [ChatController::class, 'messages'])->middleware(['ai.consent', 'throttle:20,1'])->name('chat.messages');
    Route::post('chat/transcribe', [ChatController::class, 'transcribe'])->middleware(['ai.consent', 'throttle:20,1'])->name('chat.transcribe');
    Route::post('chat/drafts/{message}/confirm', [ChatController::class, 'confirm'])->name('chat.confirm');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
