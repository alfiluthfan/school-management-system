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
use App\Http\Controllers\Web\PortalAnnouncementController;
use App\Http\Controllers\Web\PortalAnnouncementActionController;
use App\Http\Controllers\Web\PortalReportingController;
use App\Http\Controllers\Web\PortalReportExportController;
use App\Http\Controllers\Web\PortalMasterDataController;
use App\Http\Controllers\Web\PortalMasterDataMutationController;
use App\Http\Controllers\Web\PortalMasterDataOptionController;

Route::get('/', fn() => redirect()->route('portal.dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [PortalAuthController::class, 'create'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('portal.login');
});

Route::middleware(['auth', EnsurePortalAccountActive::class])->group(function (): void {

    Route::get('/master-data/options', PortalMasterDataOptionController::class)
        ->middleware('throttle:60,1')->name('portal.master-data.options');

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

    Route::get('/announcements', [PortalAnnouncementController::class, 'index'])
        ->name('portal.announcements.index');
    Route::get('/announcements/create', [PortalAnnouncementController::class, 'create'])
        ->name('portal.announcements.create');
    Route::post('/announcements', [PortalAnnouncementActionController::class, 'store'])
        ->name('portal.announcements.store');
    Route::get('/announcements/{announcement}', [PortalAnnouncementController::class, 'show'])
        ->middleware('can:view,announcement')->name('portal.announcements.show');
    Route::get('/announcements/{announcement}/edit', [PortalAnnouncementController::class, 'edit'])
        ->middleware('can:update,announcement')->name('portal.announcements.edit');
    Route::patch('/announcements/{announcement}', [PortalAnnouncementActionController::class, 'update'])
        ->middleware('can:update,announcement')->name('portal.announcements.update');
    Route::post('/announcements/{announcement}/publish', [PortalAnnouncementActionController::class, 'publish'])
        ->middleware('can:publish,announcement')->name('portal.announcements.publish');
    Route::post('/announcements/{announcement}/archive', [PortalAnnouncementActionController::class, 'archive'])
        ->middleware('can:archive,announcement')->name('portal.announcements.archive');

    Route::get('/reports', [PortalReportingController::class, 'index'])
        ->name('portal.reports.index');
    Route::post('/reports/exports', [PortalReportExportController::class, 'store'])
        ->middleware('throttle:6,1')->name('portal.reports.exports.store');
    Route::get('/reports/exports/{reportExport}/status', [PortalReportExportController::class, 'status'])
        ->name('portal.reports.exports.status');
    Route::get('/reports/exports/{reportExport}/download', [PortalReportExportController::class, 'download'])
        ->name('portal.reports.exports.download');

    Route::get('/master-data', [PortalMasterDataController::class, 'index'])
        ->name('portal.master-data.index');
    Route::post('/master-data/{kind}', [PortalMasterDataMutationController::class, 'store'])
        ->name('portal.master-data.store');
    Route::patch('/master-data/{kind}/{ref}', [PortalMasterDataMutationController::class, 'update'])
        ->name('portal.master-data.update');
    Route::patch('/master-data/users/{user}/state', [PortalMasterDataMutationController::class, 'userState'])
        ->name('portal.master-data.users.state');
    Route::post('/master-data/users/{user}/reset-password', [PortalMasterDataMutationController::class, 'resetPassword'])
        ->middleware('throttle:5,1')->name('portal.master-data.users.reset-password');
    Route::post('/master-data/years/{year}/activate', [PortalMasterDataMutationController::class, 'activateYear'])
        ->name('portal.master-data.years.activate');
    Route::post('/master-data/students/{student}/enrollments', [PortalMasterDataMutationController::class, 'enroll'])
        ->name('portal.master-data.students.enroll');
    Route::post('/master-data/parents/{guardian}/students', [PortalMasterDataMutationController::class, 'linkParent'])
        ->name('portal.master-data.parents.students.store');

    Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
});
