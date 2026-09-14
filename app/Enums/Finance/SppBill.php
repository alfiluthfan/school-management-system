<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Academic\Student;
use App\Models\Academic\AcademicYear; // Pastikan model ini sudah ada
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

    // Implementasi casts sesuai spesifikasi pengujian
    protected function casts(): array
    {
        return [
            'status'        => SppBillStatus::class,
            'billing_month' => 'integer',
            'billing_year'  => 'integer',
            'amount'        => 'decimal:2',
            'paid_amount'   => 'decimal:2',
            'due_date'      => 'date',
        ];
    }

    /**
     * Relasi ke data Siswa
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Relasi ke Tahun Ajaran
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Relasi ke Pembayaran SPP (SppPayment)
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SppPayment::class);
    }
}
