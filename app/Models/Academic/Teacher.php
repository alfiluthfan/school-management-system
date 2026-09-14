<?php

namespace App\Models\Academic;

use App\Models\Attendance\TeacherAttendance;
use App\Models\Attendance\TeacherLeave;
use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'nip',
        'employee_number',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'employment_status',
        'join_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'join_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(TeacherLeave::class);
    }
}
