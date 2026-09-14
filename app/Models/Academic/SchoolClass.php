<?php

namespace App\Models\Academic;

use App\Models\Attendance\StudentAttendance;
use App\Models\Communication\Announcement;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'homeroom_teacher_id',
        'code',
        'name',
        'grade_level',
        'major',
        'status',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class, 'class_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_class_enrollments',
            'class_id',
            'student_id'
        )
            ->withPivot([
                'joined_at',
                'left_at',
                'status',
            ])
            ->withTimestamps();
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'class_id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'class_id');
    }
}
