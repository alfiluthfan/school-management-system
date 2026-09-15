<?php

namespace App\Models\Communication;

use App\Enums\Communication\AnnouncementStatus;
use App\Enums\Communication\AnnouncementTargetScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Pivots\AnnouncementRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'created_by',
        'class_id',
        'title',
        'content',
        'target_scope',
        'publish_at',
        'expired_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_scope' =>
                AnnouncementTargetScope::class,
            'status' =>
                AnnouncementStatus::class,
            'publish_at' =>
                'datetime',
            'expired_at' =>
                'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(
            SchoolClass::class,
            'class_id'
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'announcement_roles'
        )->using(AnnouncementRole::class);
    }
}
