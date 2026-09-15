<?php

use App\Http\Controllers\Api\V1\SppPaymentCorrectionApprovalController;
use App\Http\Controllers\Api\V1\SppPaymentVoidApprovalController;
use App\Http\Controllers\Api\V1\TeacherLeaveApprovalController;
use Illuminate\Support\Facades\Route;

/* Merge inside existing Route::prefix('v1')->middleware('auth')->group(...) */

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
