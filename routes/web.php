<?php

use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\PortalDashboardController;
use App\Http\Controllers\Web\PortalModulesController;
use App\Http\Middleware\EnsurePortalAccountActive;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PortalAttendanceController;
use App\Http\Controllers\Web\PortalAttendanceActionController;

Route::get('/', fn () => redirect()->route('portal.dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('portal.login');
});

Route::middleware(['auth', EnsurePortalAccountActive::class])->group(function (): void {
    Route::get('/dashboard', PortalDashboardController::class)->name('portal.dashboard');
    Route::get('/modules', PortalModulesController::class)->name('portal.modules');

    Route::get('/attendance', PortalAttendanceController::class)
        ->name('portal.attendance.index');
    Route::post('/attendance/students/check-in', [PortalAttendanceActionController::class, 'studentCheckIn'])
        ->name('portal.attendance.students.check-in');
    Route::post('/attendance/students/check-out', [PortalAttendanceActionController::class, 'studentCheckOut'])
        ->name('portal.attendance.students.check-out');
    Route::post('/attendance/teachers/check-in', [PortalAttendanceActionController::class, 'teacherCheckIn'])
        ->name('portal.attendance.teachers.check-in');
    Route::post('/attendance/teachers/check-out', [PortalAttendanceActionController::class, 'teacherCheckOut'])
        ->name('portal.attendance.teachers.check-out');

    Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
});
