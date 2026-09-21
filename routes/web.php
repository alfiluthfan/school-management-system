<?php

use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\PortalDashboardController;
use App\Http\Controllers\Web\PortalModulesController;
use App\Http\Middleware\EnsurePortalAccountActive;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PortalAttendanceController;
use App\Http\Controllers\Web\PortalAttendanceActionController;
use App\Http\Controllers\Web\PortalApprovalController;
use App\Http\Controllers\Web\PortalApprovalDecisionController;
use App\Http\Controllers\Web\PortalFinanceController;
use App\Http\Controllers\Web\PortalFinanceActionController;

Route::get('/', fn() => redirect()->route('portal.dashboard'));

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

    Route::get('/approvals', [PortalApprovalController::class, 'index'])
        ->name('portal.approvals.index');
    Route::get('/approvals/{approval}', [PortalApprovalController::class, 'show'])
        ->middleware('can:view,approval')->name('portal.approvals.show');
    Route::post('/approvals/{approval}/approve', [PortalApprovalDecisionController::class, 'approve'])
        ->middleware(['can:view,approval', 'can:approve,approval'])
        ->name('portal.approvals.approve');
    Route::post('/approvals/{approval}/reject', [PortalApprovalDecisionController::class, 'reject'])
        ->middleware(['can:view,approval', 'can:reject,approval'])
        ->name('portal.approvals.reject');


    Route::get('/finance', [PortalFinanceController::class, 'index'])->name('portal.finance.index');
    Route::get('/finance/savings/{savingAccount}', [PortalFinanceController::class, 'saving'])
        ->middleware('can:view,savingAccount')->name('portal.finance.savings.show');
    Route::post('/finance/savings/{savingAccount}/deposit', [PortalFinanceActionController::class, 'deposit'])
        ->middleware(['can:view,savingAccount', 'can:deposit,savingAccount'])->name('portal.finance.savings.deposit');
    Route::post('/finance/savings/{savingAccount}/withdraw', [PortalFinanceActionController::class, 'withdraw'])
        ->middleware(['can:view,savingAccount', 'can:withdraw,savingAccount'])->name('portal.finance.savings.withdraw');
    Route::post(
        '/finance/savings/{savingAccount}/transactions/{savingTransaction}/reversal-requests',
        [PortalFinanceActionController::class, 'requestReversal']
    )
        ->middleware(['can:view,savingAccount', 'can:view,savingTransaction', 'can:void,savingTransaction'])
        ->name('portal.finance.savings.reversal-requests.store');
    Route::get('/finance/spp/{sppBill}', [PortalFinanceController::class, 'spp'])
        ->middleware('can:view,sppBill')->name('portal.finance.spp.show');
    Route::post('/finance/spp/{sppBill}/payments', [PortalFinanceActionController::class, 'pay'])
        ->middleware('can:view,sppBill')->name('portal.finance.spp.payments.store');
    Route::post(
        '/finance/spp/{sppBill}/payments/{sppPayment}/void-requests',
        [PortalFinanceActionController::class, 'requestPaymentVoid']
    )
        ->middleware(['can:view,sppBill', 'can:view,sppPayment', 'can:void,sppPayment'])
        ->name('portal.finance.spp.void-requests.store');

    Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
});
