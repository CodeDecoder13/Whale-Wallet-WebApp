<?php

use App\Http\Controllers\Settings\AccountResetController;
use App\Http\Controllers\Settings\DataController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TokenController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('settings/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::patch('settings/digest', [ProfileController::class, 'digest'])->name('profile.digest');
    Route::post('settings/digest/test', [ProfileController::class, 'digestTest'])->middleware('throttle:3,1')->name('profile.digest-test');
    Route::patch('settings/payday', [ProfileController::class, 'payday'])->name('profile.payday');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('settings/reset/code', [AccountResetController::class, 'code'])->middleware('throttle:3,1')->name('account-reset.code');
    Route::post('settings/reset', [AccountResetController::class, 'reset'])->middleware('throttle:6,1')->name('account-reset.store');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::put('settings/pin', [SecurityController::class, 'pin'])->middleware('throttle:6,1')->name('user-pin.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/data', [DataController::class, 'index'])->name('data.index');
    Route::get('settings/data/export', [DataController::class, 'export'])->name('data.export');
    Route::post('settings/data/import', [DataController::class, 'import'])->middleware('throttle:6,1')->name('data.import');
    Route::delete('settings/data/chat', [DataController::class, 'clearChat'])->name('data.clear-chat');

    Route::get('settings/tokens', [TokenController::class, 'index'])->name('tokens.index');
    Route::delete('settings/tokens/{token}', [TokenController::class, 'destroy'])->name('tokens.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
