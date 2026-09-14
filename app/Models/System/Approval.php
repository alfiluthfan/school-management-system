<?php

namespace App\Models\System;

use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'requested_by',
        'reviewed_by',
        'module',
        'entity_type',
        'entity_id',
        'action',
        'reason',
        'request_payload',
        'status',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
