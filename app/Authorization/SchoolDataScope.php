<?php

namespace App\Authorization;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use Illuminate\Database\Eloquent\Builder;

final class SchoolDataScope
{
    public static function students(User $user): Builder
    {
        $query = Student::query();

        if ($user->hasPermission('student.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('student.view.own')) {
                $scope->orWhere('user_id', $user->id);
            }

            if ($user->hasPermission('student.view.child') && $user->guardian) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }

            if ($user->hasPermission('student.view.class') && $user->teacher) {
                $teacherId = $user->teacher->id;

                $scope->orWhereHas(
                    'enrollments',
                    fn(Builder $query) => $query
                        ->where('status', 'ACTIVE')
                        ->whereHas(
                            'schoolClass',
                            fn(Builder $classQuery) => $classQuery
                                ->where('homeroom_teacher_id', $teacherId)
                        )
                );
            }
        });
    }

    public static function schoolClasses(User $user): Builder
    {
        $query = SchoolClass::query();

        if ($user->hasPermission('class.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('class.view.assigned') && $user->teacher) {
                $scope->orWhere('homeroom_teacher_id', $user->teacher->id);
            }

            if ($user->hasPermission('class.view.own') && $user->student) {
                $studentId = $user->student->id;

                $scope->orWhereHas(
                    'enrollments',
                    fn(Builder $query) => $query
                        ->where('student_id', $studentId)
                        ->where('status', 'ACTIVE')
                );
            }

            if ($user->hasPermission('class.view.child') && $user->guardian) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'enrollments',
                    fn(Builder $query) => $query
                        ->where('status', 'ACTIVE')
                        ->whereHas(
                            'student.guardians',
                            fn(Builder $guardianQuery) => $guardianQuery
                                ->where('parents.id', $guardianId)
                        )
                );
            }
        });
    }

    public static function studentAttendances(User $user): Builder
    {
        $query = StudentAttendance::query();

        if ($user->hasPermission('student-attendance.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('student-attendance.view.own')) {
                $scope->orWhereHas(
                    'student',
                    fn(Builder $query) => $query->where('user_id', $user->id)
                );
            }

            if (
                $user->hasPermission('student-attendance.view.child')
                && $user->guardian
            ) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'student.guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }

            if (
                $user->hasPermission('student-attendance.view.class')
                && $user->teacher
            ) {
                $scope->orWhereHas(
                    'schoolClass',
                    fn(Builder $query) => $query
                        ->where('homeroom_teacher_id', $user->teacher->id)
                );
            }
        });
    }

    public static function savingAccounts(User $user): Builder
    {
        $query = SavingAccount::query();

        if ($user->hasPermission('saving.balance.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('saving.balance.view.own')) {
                $scope->orWhereHas(
                    'student',
                    fn(Builder $query) => $query->where('user_id', $user->id)
                );
            }

            if (
                $user->hasPermission('saving.balance.view.child')
                && $user->guardian
            ) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'student.guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }
        });
    }

    public static function savingTransactions(User $user): Builder
    {
        $query = SavingTransaction::query();

        if ($user->hasPermission('saving.transaction.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('saving.transaction.view.own')) {
                $scope->orWhereHas(
                    'savingAccount.student',
                    fn(Builder $query) => $query->where('user_id', $user->id)
                );
            }

            if (
                $user->hasPermission('saving.transaction.view.child')
                && $user->guardian
            ) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'savingAccount.student.guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }
        });
    }

    public static function sppBills(User $user): Builder
    {
        $query = SppBill::query();

        if ($user->hasPermission('spp.bill.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('spp.bill.view.own')) {
                $scope->orWhereHas(
                    'student',
                    fn(Builder $query) => $query->where('user_id', $user->id)
                );
            }

            if ($user->hasPermission('spp.bill.view.child') && $user->guardian) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'student.guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }
        });
    }

    public static function sppPayments(User $user): Builder
    {
        $query = SppPayment::query();

        if ($user->hasPermission('spp.payment.view.all')) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            if ($user->hasPermission('spp.payment.view.own')) {
                $scope->orWhereHas(
                    'bill.student',
                    fn(Builder $query) => $query->where('user_id', $user->id)
                );
            }

            if (
                $user->hasPermission('spp.payment.view.child')
                && $user->guardian
            ) {
                $guardianId = $user->guardian->id;

                $scope->orWhereHas(
                    'bill.student.guardians',
                    fn(Builder $query) => $query->where('parents.id', $guardianId)
                );
            }
        });
    }
}
