<?php

namespace App\Models\Pivots;

use App\Models\Auth\Role;
use App\Models\Communication\Announcement;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(incrementing: true, timestamps: false)]
class AnnouncementRole extends Pivot
{
    protected $table = 'announcement_roles';

    protected $fillable = [
        'announcement_id',
        'role_id',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
