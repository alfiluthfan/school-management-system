<?php

use App\Http\Controllers\Api\V1\SavingAccountController;
use App\Http\Controllers\Api\V1\SavingTransactionController;
use App\Http\Controllers\Api\V1\SppBillController;
use App\Http\Controllers\Api\V1\SppPaymentController;
use App\Http\Controllers\Api\V1\StudentAttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\NotificationMonitoringController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\SavingReversalApprovalController;
use App\Http\Controllers\Api\V1\AttendanceCorrectionApprovalController;
use App\Http\Controllers\Api\V1\TeacherLeaveApprovalController;
use App\Http\Controllers\Api\V1\SppPaymentVoidApprovalController;
use App\Http\Controllers\Api\V1\SppPaymentCorrectionApprovalController;
use App\Http\Controllers\Api\V1\TeacherAttendanceController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReportController;

Route::prefix('v1')
    ->middleware('auth')
    ->group(function (): void {
        Route::prefix('student-attendances')->group(function (): void {
            Route::post('/check-in', [StudentAttendanceController::class, 'checkIn'])
                ->name('api.v1.student-attendances.check-in');

            Route::post('/check-out', [StudentAttendanceController::class, 'checkOut'])
                ->name('api.v1.student-attendances.check-out');

            Route::get('/', [StudentAttendanceController::class, 'index'])
                ->name('api.v1.student-attendances.index');

            Route::get('/{studentAttendance}', [StudentAttendanceController::class, 'show'])
                ->name('api.v1.student-attendances.show');

            Route::post(
                '/student-attendances/{studentAttendance}/correction-requests',
                [
                    AttendanceCorrectionApprovalController::class,
                    'store',
                ]
            )->name(
                'api.v1.student-attendances.correction-requests.store'
            );
        });
        Route::prefix('admin/notifications')
            ->group(function (): void {

                Route::get(
                    '/',
                    [
                        NotificationMonitoringController::class,
                        'index',
                    ]
                )->name(
                    'api.v1.admin.notifications.index'
                );

                // Harus di atas /{notificationLog}
                Route::get(
                    '/stats',
                    [
                        NotificationMonitoringController::class,
                        'stats',
                    ]
                )->name(
                    'api.v1.admin.notifications.stats'
                );

                Route::get(
                    '/{notificationLog}',
                    [
                        NotificationMonitoringController::class,
                        'show',
                    ]
                )->name(
                    'api.v1.admin.notifications.show'
                );

                Route::post(
                    '/{notificationLog}/retry',
                    [
                        NotificationMonitoringController::class,
                        'retry',
                    ]
                )->name(
                    'api.v1.admin.notifications.retry'
                );
            });

        Route::get(
            '/dashboard/overview',
            [
                DashboardController::class,
                'overview',
            ]
        )->name(
            'api.v1.dashboard.overview'
        );

        Route::prefix('reports')
            ->group(function (): void {
                Route::get(
                    '/attendance/students',
                    [
                        ReportController::class,
                        'studentAttendance',
                    ]
                )->name(
                    'api.v1.reports.attendance.students'
                );

                Route::get(
                    '/attendance/teachers',
                    [
                        ReportController::class,
                        'teacherAttendance',
                    ]
                )->name(
                    'api.v1.reports.attendance.teachers'
                );

                Route::get(
                    '/savings',
                    [
                        ReportController::class,
                        'savings',
                    ]
                )->name(
                    'api.v1.reports.savings'
                );

                Route::get(
                    '/spp',
                    [
                        ReportController::class,
                        'spp',
                    ]
                )->name(
                    'api.v1.reports.spp'
                );
            });


        Route::prefix('announcements')
            ->group(function (): void {
                /*
         * Static routes MUST stay before /{announcement}.
         */
                Route::get(
                    '/mine',
                    [
                        AnnouncementController::class,
                        'mine',
                    ]
                )->name(
                    'api.v1.announcements.mine'
                );

                Route::get(
                    '/',
                    [
                        AnnouncementController::class,
                        'index',
                    ]
                )->name(
                    'api.v1.announcements.index'
                );

                Route::post(
                    '/',
                    [
                        AnnouncementController::class,
                        'store',
                    ]
                )->name(
                    'api.v1.announcements.store'
                );

                Route::get(
                    '/{announcement}',
                    [
                        AnnouncementController::class,
                        'show',
                    ]
                )->name(
                    'api.v1.announcements.show'
                );

                Route::patch(
                    '/{announcement}',
                    [
                        AnnouncementController::class,
                        'update',
                    ]
                )->name(
                    'api.v1.announcements.update'
                );

                Route::post(
                    '/{announcement}/publish',
                    [
                        AnnouncementController::class,
                        'publish',
                    ]
                )->name(
                    'api.v1.announcements.publish'
                );

                Route::post(
                    '/{announcement}/archive',
                    [
                        AnnouncementController::class,
                        'archive',
                    ]
                )->name(
                    'api.v1.announcements.archive'
                );
            });

        Route::get('/saving-accounts', [SavingAccountController::class, 'index'])
            ->name('api.v1.saving-accounts.index');

        Route::get('/saving-accounts/{savingAccount}', [SavingAccountController::class, 'show'])
            ->name('api.v1.saving-accounts.show');

        Route::get(
            '/saving-accounts/{savingAccount}/transactions',
            [SavingTransactionController::class, 'index']
        )->name('api.v1.saving-accounts.transactions.index');

        Route::post(
            '/saving-accounts/{savingAccount}/deposits',
            [SavingTransactionController::class, 'deposit']
        )->name('api.v1.saving-accounts.deposits.store');

        Route::post(
            '/saving-accounts/{savingAccount}/withdrawals',
            [SavingTransactionController::class, 'withdraw']
        )->name('api.v1.saving-accounts.withdrawals.store');

        Route::get(
            '/saving-transactions/{savingTransaction}',
            [SavingTransactionController::class, 'show']
        )->name('api.v1.saving-transactions.show');

        // Route::post(
        //     '/saving-transactions/{savingTransaction}/reversal',
        //     [SavingTransactionController::class, 'reverse']
        // )->name('api.v1.saving-transactions.reversal.store');
        Route::post(
            '/saving-transactions/{savingTransaction}/reversal-requests',
            [
                SavingReversalApprovalController::class,
                'store',
            ]
        )->name(
            'api.v1.saving-transactions.reversal-requests.store'
        );

        Route::prefix('approvals')->group(function (): void {
            Route::get(
                '/',
                [
                    ApprovalController::class,
                    'index',
                ]
            )->name('api.v1.approvals.index');

            Route::get(
                '/{approval}',
                [
                    ApprovalController::class,
                    'show',
                ]
            )->name('api.v1.approvals.show');

            Route::post(
                '/{approval}/approve',
                [
                    ApprovalController::class,
                    'approve',
                ]
            )->name('api.v1.approvals.approve');

            Route::post(
                '/{approval}/reject',
                [
                    ApprovalController::class,
                    'reject',
                ]
            )->name('api.v1.approvals.reject');
        });

        Route::prefix('teacher-attendances')
            ->group(function (): void {
                /*
         * Static routes MUST stay before
         * /{teacherAttendance}.
         */
                Route::post(
                    '/check-in',
                    [
                        TeacherAttendanceController::class,
                        'checkIn',
                    ]
                )->name(
                    'api.v1.teacher-attendances.check-in'
                );

                Route::post(
                    '/check-out',
                    [
                        TeacherAttendanceController::class,
                        'checkOut',
                    ]
                )->name(
                    'api.v1.teacher-attendances.check-out'
                );

                Route::get(
                    '/',
                    [
                        TeacherAttendanceController::class,
                        'index',
                    ]
                )->name(
                    'api.v1.teacher-attendances.index'
                );

                Route::get(
                    '/{teacherAttendance}',
                    [
                        TeacherAttendanceController::class,
                        'show',
                    ]
                )->name(
                    'api.v1.teacher-attendances.show'
                );
            });


        Route::get('/spp-bills', [SppBillController::class, 'index'])
            ->name('api.v1.spp-bills.index');

        Route::get('/spp-bills/{sppBill}', [SppBillController::class, 'show'])
            ->name('api.v1.spp-bills.show');

        Route::get(
            '/spp-bills/{sppBill}/payments',
            [SppPaymentController::class, 'index']
        )->name('api.v1.spp-bills.payments.index');

        Route::post(
            '/spp-bills/{sppBill}/payments',
            [SppPaymentController::class, 'store']
        )->name('api.v1.spp-bills.payments.store');

        Route::get(
            '/spp-payments/{sppPayment}',
            [SppPaymentController::class, 'show']
        )->name('api.v1.spp-payments.show');

        Route::post(
            '/teacher-leaves',
            [TeacherLeaveApprovalController::class, 'store']
        )->name('api.v1.teacher-leaves.store');

        Route::post(
            '/spp-payments/{sppPayment}/void-requests',
            [SppPaymentVoidApprovalController::class, 'store']
        )->name('api.v1.spp-payments.void-requests.store');

        Route::post(
            '/spp-payments/{sppPayment}/correction-requests',
            [SppPaymentCorrectionApprovalController::class, 'store']
        )->name('api.v1.spp-payments.correction-requests.store');
    });
