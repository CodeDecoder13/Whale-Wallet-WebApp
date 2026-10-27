<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\GateController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// /admin: 404 for anyone who is not an admin; the gate (PIN + emailed code) opens it for 2 hours
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('gate', [GateController::class, 'show'])->name('gate');
    Route::post('gate/code', [GateController::class, 'code'])->middleware('throttle:3,1')->name('gate.code');
    Route::post('gate', [GateController::class, 'verify'])->middleware('throttle:6,1')->name('gate.verify');

    // everything that shows user data is for owners only (a "support" admin gets the support chat, added later)
    Route::middleware(['admin.gate', 'admin:owner'])->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('loans', [LoanController::class, 'index'])->name('loans');
        Route::get('activity', [ActivityController::class, 'index'])->name('activity');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions');
        Route::get('transactions/export', [TransactionController::class, 'export'])->middleware('throttle:6,1')->name('transactions.export');
        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('receipts.show');
        Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->whereNumber('receipt')->name('receipts.image');
        Route::get('chats', [ChatController::class, 'index'])->name('chats');
        Route::get('chats/{thread}', [ChatController::class, 'show'])->whereNumber('thread')->name('chats.show');
    });
});
