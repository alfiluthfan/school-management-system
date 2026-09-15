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
        // Kode Morph Map diletakkan di dalam method boot()
        Relation::enforceMorphMap([
            'student_attendance' => StudentAttendance::class,
            'teacher_attendance' => TeacherAttendance::class,
            'saving_account'     => SavingAccount::class,
            'teacher_leave' => TeacherLeave::class,
            'saving_transaction' => SavingTransaction::class,
            'spp_bill'           => SppBill::class,
            'spp_payment' => SppPayment::class,
            'notification_log'   => NotificationLog::class,
        ]);
    }
}
