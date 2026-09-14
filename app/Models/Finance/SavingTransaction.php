<?php

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\Finance\SavingTransactionType;

class SavingTransaction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'transaction_number',
        'saving_account_id',
        'created_by',
        'reference_transaction_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'status',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => SavingTransactionType::class,
            'status' => SavingTransactionStatus::class,

            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',

            'transaction_date' => 'datetime',
        ];
    }

    public function savingAccount(): BelongsTo
    {
        return $this->belongsTo(SavingAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referenceTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_transaction_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reference_transaction_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'entity');
    }
}
