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
    });
