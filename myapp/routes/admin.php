<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\GateController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\ReleaseNoteController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserActionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// The admin: 404 for anyone who is not an admin; the gate (PIN + emailed code) opens it for 2 hours.
// On its own host (ADMIN_DOMAIN) the paths start at / ; without one they sit under /admin on the main host.
// This file is loaded first in web.php: a host route must be registered before the main site's routes with the same path ('/', 'transactions').
$admin = ($domain = config('app.admin_domain')) ? Route::domain($domain) : Route::prefix('admin');

$admin->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('gate', [GateController::class, 'show'])->name('gate');
    Route::post('gate/code', [GateController::class, 'code'])->middleware('throttle:3,1')->name('gate.code');
    Route::post('gate', [GateController::class, 'verify'])->middleware('throttle:6,1')->name('gate.verify');

    // the support inbox is open to every admin (owners and support staff)
    Route::middleware('admin.gate')->group(function () {
        Route::get('support', [SupportController::class, 'index'])->name('support');
        Route::post('support/{thread}/messages', [SupportController::class, 'reply'])->whereNumber('thread')->middleware('throttle:30,1')->name('support.reply');
        Route::post('support/{thread}/status', [SupportController::class, 'status'])->whereNumber('thread')->name('support.status');
    });

    // everything that shows user data is for owners only (a "support" admin gets the support chat, added later)
    Route::middleware(['admin.gate', 'admin:owner'])->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('loans', [LoanController::class, 'index'])->name('loans');
        Route::get('activity', [ActivityController::class, 'index'])->name('activity');
        Route::get('audit', [AuditController::class, 'index'])->name('audit');

        Route::get('release-notes', [ReleaseNoteController::class, 'index'])->name('release-notes');
        Route::get('release-notes/create', [ReleaseNoteController::class, 'create'])->name('release-notes.create');
        Route::post('release-notes', [ReleaseNoteController::class, 'store'])->name('release-notes.store');
        Route::get('release-notes/{note}/edit', [ReleaseNoteController::class, 'edit'])->whereNumber('note')->name('release-notes.edit');
        Route::put('release-notes/{note}', [ReleaseNoteController::class, 'update'])->whereNumber('note')->name('release-notes.update');
        Route::delete('release-notes/{note}', [ReleaseNoteController::class, 'destroy'])->whereNumber('note')->name('release-notes.destroy');
        Route::post('release-notes/{note}/publish', [ReleaseNoteController::class, 'publish'])->whereNumber('note')->name('release-notes.publish');
        Route::post('release-notes/{note}/test', [ReleaseNoteController::class, 'test'])->whereNumber('note')->middleware('throttle:6,1')->name('release-notes.test');

        Route::post('users/{user}/suspend', [UserActionController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/unsuspend', [UserActionController::class, 'unsuspend'])->name('users.unsuspend');
        Route::post('users/{user}/sign-out', [UserActionController::class, 'signOut'])->name('users.sign-out');
        Route::post('users/{user}/resend-code', [UserActionController::class, 'resendCode'])->middleware('throttle:6,1')->name('users.resend-code');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions');
        Route::get('transactions/export', [TransactionController::class, 'export'])->middleware('throttle:6,1')->name('transactions.export');
        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts');
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('receipts.show');
        Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->whereNumber('receipt')->name('receipts.image');
        Route::get('chats', [ChatController::class, 'index'])->name('chats');
        Route::get('chats/{thread}', [ChatController::class, 'show'])->whereNumber('thread')->name('chats.show');
    });
});
