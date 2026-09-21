<?php

namespace App\Enums\Reporting;

enum ReportType: string
{
    case StudentAttendance = 'student_attendance';
    case TeacherAttendance = 'teacher_attendance';
    case Savings = 'savings';
    case Spp = 'spp';

    public function permission(): string
    {
        return match ($this) {
            self::StudentAttendance => 'report.attendance.student',
            self::TeacherAttendance => 'report.attendance.teacher',
            self::Savings => 'report.saving',
            self::Spp => 'report.spp',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::StudentAttendance => 'Laporan Absensi Siswa',
            self::TeacherAttendance => 'Laporan Absensi Guru',
            self::Savings => 'Laporan Tabungan Siswa',
            self::Spp => 'Laporan SPP',
        };
    }
}
