<?php

namespace App\Models\Communication;

use App\Enums\Communication\NotificationChannel;
use App\Enums\Communication\NotificationStatus;
use App\Enums\Communication\NotificationType;
use App\Models\Academic\Student;
use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasPublicUuid;

    protected $table = 'notifications';

    protected $fillable = [
        'dedupe_key',
        'recipient_user_id',
        'student_id',
        'type',
        'channel',
        'recipient',
        'subject',
        'message',
        'provider_message_id',
        'status',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'retry_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,

            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',

            'retry_count' => 'integer',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recipient_user_id'
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
