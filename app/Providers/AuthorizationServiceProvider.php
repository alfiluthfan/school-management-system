<?php

namespace App\Providers;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use App\Policies\ApprovalPolicy;
use App\Policies\SavingAccountPolicy;
use App\Policies\SavingTransactionPolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\SppBillPolicy;
use App\Policies\SppPaymentPolicy;
use App\Policies\StudentAttendancePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherAttendancePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Attendance\TeacherLeave;
use App\Policies\TeacherLeavePolicy;
use App\Models\Communication\Announcement;
use App\Policies\AnnouncementPolicy;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Permission abilities such as "dashboard.admin" or
         * "spp.payment.create" may be checked directly with:
         *
         *     Gate::authorize('spp.payment.create');
         *     $user->can('dashboard.admin');
         *
         * IMPORTANT: return null when the user does not own the permission,
         * so model policy checks such as "view" / "update" can continue.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasPermission($ability) ? true : null;
        });

        /*
         * Explicit registration keeps policy mapping obvious even though
         * Laravel can discover conventional policies automatically.
         */
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(SchoolClass::class, SchoolClassPolicy::class);
        Gate::policy(StudentAttendance::class, StudentAttendancePolicy::class);
        Gate::policy(TeacherAttendance::class, TeacherAttendancePolicy::class);
        Gate::policy(SavingAccount::class, SavingAccountPolicy::class);
        Gate::policy(SavingTransaction::class, SavingTransactionPolicy::class);
        Gate::policy(SppBill::class, SppBillPolicy::class);
        Gate::policy(SppPayment::class, SppPaymentPolicy::class);
        Gate::policy(Approval::class, ApprovalPolicy::class);
        Gate::policy(TeacherLeave::class, TeacherLeavePolicy::class);
        Gate::policy(
            Announcement::class,
            AnnouncementPolicy::class
        );
    }
}
