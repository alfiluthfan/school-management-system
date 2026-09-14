<?php

use App\Http\Controllers\Api\V1\SavingAccountController;
use App\Http\Controllers\Api\V1\SavingTransactionController;
use App\Http\Controllers\Api\V1\SppBillController;
use App\Http\Controllers\Api\V1\SppPaymentController;
use App\Http\Controllers\Api\V1\StudentAttendanceController;
use Illuminate\Support\Facades\Route;

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

        Route::post(
            '/saving-transactions/{savingTransaction}/reversal',
            [SavingTransactionController::class, 'reverse']
        )->name('api.v1.saving-transactions.reversal.store');

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
