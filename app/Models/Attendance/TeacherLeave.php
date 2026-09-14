<?php

namespace App\Models\Attendance;

use App\Models\Academic\Teacher;
use App\Models\Concerns\HasPublicUuid;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TeacherLeave extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'teacher_id',
        'start_date',
        'end_date',
        'leave_type',
        'reason',
        'attachment_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'entity');
    }
}
