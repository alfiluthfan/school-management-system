<?php

use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Attendance\TeacherLeave;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
use Illuminate\Database\Eloquent\Relations\Relation;

// Put this inside App\Providers\AppServiceProvider::boot()
Relation::enforceMorphMap([
    'student_attendance' => StudentAttendance::class,
    'teacher_attendance' => TeacherAttendance::class,
    'teacher_leave' => TeacherLeave::class,
    'saving_transaction' => SavingTransaction::class,
    'spp_payment' => SppPayment::class,
]);
