<?php

namespace App\Models\Attendance;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\Attendance\AttendanceStatus;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TeacherAttendance extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'teacher_id',
        'attendance_schedule_id',
        'school_location_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'location_accuracy',
        'distance_from_school',
        'status',
        'late_minutes',
        'source',
        'notes',
        'corrected_by',
        'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' =>
            'date',
            'check_in_at' =>
            'datetime',
            'check_out_at' =>
            'datetime',
            'check_in_latitude' =>
            'decimal:7',
            'check_in_longitude' =>
            'decimal:7',
            'check_out_latitude' =>
            'decimal:7',
            'check_out_longitude' =>
            'decimal:7',
            'location_accuracy' =>
            'decimal:2',
            'distance_from_school' =>
            'decimal:2',
            'status' =>
            AttendanceStatus::class,
            'late_minutes' =>
            'integer',
            'source' =>
            AttendanceSource::class,
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            Teacher::class
        );
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(
            AttendanceSchedule::class,
            'attendance_schedule_id'
        );
    }

    public function schoolLocation(): BelongsTo
    {
        return $this->belongsTo(
            SchoolLocation::class
        );
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'corrected_by'
        );
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(
            Approval::class,
            'entity'
        );
    }
}
