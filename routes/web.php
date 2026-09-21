<?php

use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\PortalDashboardController;
use App\Http\Controllers\Web\PortalModulesController;
use App\Http\Middleware\EnsurePortalAccountActive;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('portal.dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('portal.login');
});

Route::middleware(['auth', EnsurePortalAccountActive::class])->group(function (): void {
    Route::get('/dashboard', PortalDashboardController::class)->name('portal.dashboard');
    Route::get('/modules', PortalModulesController::class)->name('portal.modules');
    Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
});
