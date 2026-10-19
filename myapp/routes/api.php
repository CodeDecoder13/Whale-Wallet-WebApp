<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\PodController;
use App\Http\Controllers\Api\RecurringItemController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Settings\DataController;
use App\Http\Controllers\TransactionController as WebTransactionController;
use Illuminate\Support\Facades\Route;

// Served at /api/v1. Receipts, chat and the CSV export reuse the web controllers: they already answer in JSON.
Route::name('api.')->prefix('v1')->group(function () {
    Route::post('auth/register', [AccountController::class, 'register'])->middleware('throttle:6,1');
    Route::post('auth/login',[AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AccountController::class, 'profile']);
        Route::patch('me/avatar', [AccountController::class, 'avatar']);
        Route::patch('me/payday', [AccountController::class, 'payday']);
        Route::patch('me/receipts', [AccountController::class, 'keepReceipts']);
        Route::patch('me/digest', [AccountController::class, 'digest']);
        Route::put('me/password', [AccountController::class, 'password'])->middleware('throttle:6,1');
        Route::delete('me', [AuthController::class, 'destroy'])->middleware('throttle:6,1');
        Route::get('export', [DataController::class, 'export']);
        Route::delete('chat', [DataController::class, 'clearChat']);

        Route::middleware('verified')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'dashboard']);
            Route::get('forecast', [DashboardController::class, 'forecast']);

            Route::apiResource('cards', CardController::class);
            Route::apiResource('pods', PodController::class);
            Route::post('pods/{pod}/fund', [PodController::class, 'fund']);
            Route::apiResource('categories', CategoryController::class);
            Route::post('onboarding', [AccountController::class, 'onboard']);
            Route::apiResource('recurring-items', RecurringItemController::class);
            Route::post('recurring-items/{recurring_item}/pay', [AccountController::class, 'pay']);
            Route::post('transactions/quick', [AccountController::class, 'quick']);
            Route::post('income', [AccountController::class, 'income']);
            Route::delete('income/{income}', [AccountController::class, 'removeIncome']);
            Route::get('receipts', [AccountController::class, 'receipts']);
            Route::delete('receipts/{receipt}', [ReceiptController::class, 'destroy']);
            Route::get('chat', [ChatController::class, 'recent']);
            Route::apiResource('transactions', TransactionController::class);
            Route::post('transfers', [TransactionController::class, 'transfer']);
            Route::get('export/transactions.csv', [WebTransactionController::class, 'export']);

            Route::post('consent/ai', [AiController::class, 'consent']);
            Route::delete('consent/ai', [AiController::class, 'revoke']);
            Route::get('recommendations', [AiController::class, 'index']);
            Route::post('recommendations/generate', [AiController::class, 'generate'])->middleware(['ai.consent', 'throttle:6,1']);
            Route::post('recommendations/{recommendation}/dismiss', [AiController::class, 'dismiss']);

            Route::post('receipts', [ReceiptController::class, 'store'])->middleware(['ai.consent', 'throttle:10,1']);
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);
            Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm']);

            Route::post('chat/threads', [ChatController::class, 'newThread']);
            Route::patch('chat/threads/{thread}', [ChatController::class, 'renameThread']);
            Route::delete('chat/threads/{thread}', [ChatController::class, 'deleteThread']);
            Route::post('chat/messages', [ChatController::class, 'messages'])->middleware(['ai.consent', 'throttle:20,1']);
            Route::post('chat/drafts/{message}/confirm', [ChatController::class, 'confirm']);

            Route::post('devices', [DeviceController::class, 'store']);
            Route::delete('devices', [DeviceController::class, 'forget']);
            Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
        });
    });
});
