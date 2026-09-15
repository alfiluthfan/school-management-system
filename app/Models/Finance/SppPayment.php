<?php

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppPaymentStatus;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SppPayment extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'payment_number',
        'receipt_number',
        'spp_bill_id',
        'created_by',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'status',
        'notes',
        'voided_by',
        'voided_at',
        'void_reason',
        'replaces_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => SppPaymentStatus::class,

            'amount' => 'decimal:2',

            'payment_date' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SppBill::class, 'spp_bill_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'entity');
    }
    public function replacesPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_payment_id');
    }

    public function replacementPayment(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_payment_id');
    }
}
