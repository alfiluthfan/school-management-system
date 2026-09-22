<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Attendance\TeacherLeave;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use App\Models\Communication\NotificationLog;
use App\Models\System\Approval;
use App\Models\Communication\Announcement;
use App\Models\Reporting\ReportExport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'student_attendance' => StudentAttendance::class,
            'teacher_attendance' => TeacherAttendance::class,
            'saving_account'     => SavingAccount::class,
            'teacher_leave' => TeacherLeave::class,
            'saving_transaction' => SavingTransaction::class,
            'spp_bill'           => SppBill::class,
            'spp_payment' => SppPayment::class,
            'notification_log'   => NotificationLog::class,
            'approval' => Approval::class,
            'announcement' => Announcement::class,
            'report_export' => ReportExport::class,
            'user' => \App\Models\Auth\User::class,
            'student' => \App\Models\Academic\Student::class,
            'teacher' => \App\Models\Academic\Teacher::class,
            'parent' => \App\Models\Academic\Guardian::class,
            'school_class' => \App\Models\Academic\SchoolClass::class,
            'academic_year' => \App\Models\Academic\AcademicYear::class,
            'student_class_enrollment' => \App\Models\Academic\StudentClassEnrollment::class,
        ]);
    }
}
