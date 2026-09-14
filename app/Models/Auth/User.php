<?php

namespace App\Models\Auth;

use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use App\Models\Academic\Teacher;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Communication\Announcement;
use App\Models\Communication\NotificationLog;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
use App\Models\Pivots\UserRole;
use App\Models\System\Approval;
use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasRolesAndPermissions;

class User extends Authenticatable
{
    use HasPublicUuid;
    use HasRolesAndPermissions;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->using(UserRole::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function createdSavingTransactions(): HasMany
    {
        return $this->hasMany(SavingTransaction::class, 'created_by');
    }

    public function createdSppPayments(): HasMany
    {
        return $this->hasMany(SppPayment::class, 'created_by');
    }

    public function voidedSppPayments(): HasMany
    {
        return $this->hasMany(SppPayment::class, 'voided_by');
    }

    public function createdAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function receivedNotifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'recipient_user_id');
    }

    public function requestedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'requested_by');
    }

    public function reviewedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'reviewed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function correctedStudentAttendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class, 'corrected_by');
    }

    public function correctedTeacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'corrected_by');
    }
}
