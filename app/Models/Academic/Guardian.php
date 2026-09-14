<?php

namespace App\Models\Academic;

use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Pivots\ParentStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'occupation',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'parent_students',
            'parent_id',
            'student_id'
        )
            ->using(ParentStudent::class)
            ->withPivot([
                'relationship',
                'is_primary_contact',
                'receive_notification',
            ])
            ->withTimestamps();
    }
}
