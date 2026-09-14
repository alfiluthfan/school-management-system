<?php

namespace App\Models\Attendance;

use App\Models\Academic\AcademicYear;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSchedule extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name',
        'attendance_type',
        'day_of_week',
        'check_in_start',
        'check_in_deadline',
        'check_in_end',
        'check_out_start',
        'check_out_end',
        'late_tolerance_minutes',
        'school_location_id',
        'academic_year_id',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function schoolLocation(): BelongsTo
    {
        return $this->belongsTo(SchoolLocation::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }
}
