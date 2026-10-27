<?php

use App\Http\Controllers\Admin\GateController;
use App\Http\Controllers\Admin\HomeController;
use Illuminate\Support\Facades\Route;

// /admin: 404 for anyone who is not an admin; the gate (PIN + emailed code) opens it for 2 hours
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('gate', [GateController::class, 'show'])->name('gate');
    Route::post('gate/code', [GateController::class, 'code'])->middleware('throttle:3,1')->name('gate.code');
    Route::post('gate', [GateController::class, 'verify'])->middleware('throttle:6,1')->name('gate.verify');

    Route::middleware('admin.gate')->group(function () {
        Route::get('/', HomeController::class)->name('home');
    });
});
