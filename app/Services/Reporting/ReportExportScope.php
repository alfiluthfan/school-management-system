<?php

namespace App\Services\Reporting;

use App\Authorization\SchoolDataScope;
use App\Enums\Reporting\ReportType;
use App\Models\Auth\User;

/** Blocks stale downloads after reassignment or change of data-read scope. */
final class ReportExportScope
{
    public function hash(User $user, ReportType $type): string
    {
        $scope = match ($type) {
            ReportType::StudentAttendance =>
                $user->hasPermission('student-attendance.view.all')
                    ? 'GLOBAL'
                    : SchoolDataScope::schoolClasses($user)->pluck('uuid')->sort()->implode('|'),
            ReportType::TeacherAttendance =>
                $user->hasPermission('teacher-attendance.view.all')
                    ? 'GLOBAL'
                    : ($user->teacher?->uuid ?? 'NONE'),
            ReportType::Savings =>
                $user->hasPermission('saving.balance.view.all')
                    && $user->hasPermission('saving.transaction.view.all')
                    ? 'GLOBAL'
                    : SchoolDataScope::savingAccounts($user)->pluck('uuid')->sort()->implode('|'),
            ReportType::Spp =>
                $user->hasPermission('spp.bill.view.all')
                    && $user->hasPermission('spp.payment.view.all')
                    ? 'GLOBAL'
                    : SchoolDataScope::sppBills($user)->pluck('uuid')->sort()->implode('|'),
        };

        return hash('sha256', $type->value.'|'.$scope);
    }
}
