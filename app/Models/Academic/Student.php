<?php

namespace App\Models\Academic;

use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Models\Communication\NotificationLog;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use App\Models\Pivots\ParentStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'nis',
        'nisn',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'admission_date',
        'graduation_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'admission_date' => 'date',
            'graduation_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(
            Guardian::class,
            'parent_students',
            'student_id',
            'parent_id'
        )
            ->using(ParentStudent::class)
            ->withPivot([
                'relationship',
                'is_primary_contact',
                'receive_notification',
                'created_at',
            ]);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'student_class_enrollments',
            'student_id',
            'class_id'
        )
            ->withPivot([
                'joined_at',
                'left_at',
                'status',
            ])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function savingAccount(): HasOne
    {
        return $this->hasOne(SavingAccount::class);
    }

    public function sppBills(): HasMany
    {
        return $this->hasMany(SppBill::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
