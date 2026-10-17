<?php

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
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::delete('me', [AuthController::class, 'destroy'])->middleware('throttle:6,1');
        Route::get('export', [DataController::class, 'export']);
        Route::delete('chat', [DataController::class, 'clearChat']);

        Route::middleware('verified')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'dashboard']);
            Route::get('forecast', [DashboardController::class, 'forecast']);

            Route::apiResource('cards', CardController::class);
            Route::apiResource('pods', PodController::class);
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('recurring-items', RecurringItemController::class);
            Route::apiResource('transactions', TransactionController::class);
            Route::post('transfers', [TransactionController::class, 'transfer']);
            Route::get('export/transactions.csv', [WebTransactionController::class, 'export']);

            Route::post('consent/ai', [AiController::class, 'consent']);
            Route::get('recommendations', [AiController::class, 'index']);
            Route::post('recommendations/generate', [AiController::class, 'generate'])->middleware(['ai.consent', 'throttle:6,1']);
            Route::post('recommendations/{recommendation}/dismiss', [AiController::class, 'dismiss']);

            Route::post('receipts', [ReceiptController::class, 'store'])->middleware(['ai.consent', 'throttle:10,1']);
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);
            Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm']);

            Route::post('chat/messages', [ChatController::class, 'messages'])->middleware(['ai.consent', 'throttle:20,1']);
            Route::post('chat/drafts/{message}/confirm', [ChatController::class, 'confirm']);

            Route::post('devices', [DeviceController::class, 'store']);
            Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
        });
    });
});
