<?php

namespace App\Models\Pivots;

use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(incrementing: true)]
class ParentStudent extends Pivot
{
    protected $table = 'parent_students';

    public const UPDATED_AT = null;

    protected $fillable = [
        'parent_id',
        'student_id',
        'relationship',
        'is_primary_contact',
        'receive_notification',
    ];

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'receive_notification' => 'boolean',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
