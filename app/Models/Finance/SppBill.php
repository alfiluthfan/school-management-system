<?php

namespace App\Models\Finance;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Student;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\Finance\SppBillStatus;

class SppBill extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'bill_number',
        'student_id',
        'academic_year_id',
        'billing_month',
        'billing_year',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SppBillStatus::class,

            'billing_month' => 'integer',
            'billing_year' => 'integer',

            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',

            'due_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SppPayment::class);
    }
}
