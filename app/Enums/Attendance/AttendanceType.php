<?php

namespace App\Enums\Attendance;

use App\Enums\Concerns\HasEnumOptions;

enum AttendanceType: string
{
    use HasEnumOptions;

    case Student = 'STUDENT';
    case Teacher = 'TEACHER';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::Teacher => 'Guru',
        };
    }
}
