<?php

namespace App\Events\Attendance;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentLateDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $studentAttendanceId
    ) {
    }
}
