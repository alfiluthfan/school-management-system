<?php

namespace App\Queries\Finance;

use App\Models\Academic\Student;
use App\Models\Auth\User;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class FinanceCashierQuery
{
    public static function authorize(?User $actor): void
    {
        abort_unless($actor !== null, 401);
        abort_unless($actor->hasPermission('student.view.all') &&
            (($actor->hasPermission('saving.deposit.create') || $actor->hasPermission('saving.withdrawal.create'))
                && $actor->hasPermission('saving.balance.view.all')
                || ($actor->hasPermission('spp.bill.view.all') && Gate::forUser($actor)->allows('create', SppPayment::class))), 403);
    }

    public function index(User $actor, ?string $term, ?string $selected): array
    {
        self::authorize($actor);
        $found = [];
        if ($term !== null && mb_strlen(trim($term)) >= 2) {
            $like = '%'.addcslashes(trim($term), '%_\\').'%';
            $found = Student::query()->where('status', 'ACTIVE')
                ->where(fn (Builder $q) => $q->where('nis', 'like', $like)
                    ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', $like)))
                ->with('user:id,name')->orderBy('nis')->limit(20)->get()
                ->map(fn (Student $student) => $this->student($student))->all();
        }
        $student = $selected ? Student::query()->where('uuid', $selected)->where('status', 'ACTIVE')
            ->with(['user:id,name', 'savingAccount', 'sppBills' => fn ($q) => $q->orderByDesc('billing_year')
                ->orderByDesc('billing_month')->limit(24)])->firstOrFail() : null;
        $account = $actor->hasPermission('saving.balance.view.all') ? $student?->savingAccount : null;
        $bills = $actor->hasPermission('spp.bill.view.all') ? $student?->sppBills : collect();
        return [
            'search' => $term ?? '', 'results' => $found,
            'can' => [
                'deposit' => $actor->hasPermission('saving.balance.view.all') && $actor->hasPermission('saving.deposit.create'),
                'withdraw' => $actor->hasPermission('saving.balance.view.all') && $actor->hasPermission('saving.withdrawal.create'),
                'pay' => $actor->hasPermission('spp.bill.view.all') && Gate::forUser($actor)->allows('create', SppPayment::class),
            ],
            'selected' => $student ? [
                ...$this->student($student),
                'account' => $account ? [
                    'uuid' => $account->uuid, 'balance' => $account->current_balance,
                    'status' => $account->status instanceof \BackedEnum ? $account->status->value : $account->status,
                ] : null,
                'savings_history' => $account && $actor->hasPermission('saving.transaction.view.all') ? SavingTransaction::query()
                    ->where('saving_account_id', $account->id)->with('creator:id,name')->orderByDesc('id')->limit(15)->get()
                    ->map(fn ($t) => [
                        'number' => $t->transaction_number, 'amount' => $t->amount,
                        'type' => $t->transaction_type instanceof \BackedEnum ? $t->transaction_type->value : $t->transaction_type,
                        'description' => $t->description, 'date' => $t->transaction_date?->toIso8601String(),
                        'balance_after' => $t->balance_after, 'recorded_by' => $t->creator?->name,
                    ])->all() : [],
                'bills' => $bills->map(fn ($bill) => [
                    'uuid' => $bill->uuid, 'period' => sprintf('%02d/%04d', $bill->billing_month, $bill->billing_year),
                    'amount' => $bill->amount, 'paid' => $bill->paid_amount,
                    'remaining' => bcsub($bill->amount, $bill->paid_amount, 2),
                    'status' => $bill->status instanceof \BackedEnum ? $bill->status->value : $bill->status,
                    'due_date' => $bill->due_date?->toDateString(),
                ])->all(),
                'payments' => $actor->hasPermission('spp.payment.view.all') ? SppPayment::query()->whereIn('spp_bill_id', $bills->pluck('id'))
                    ->with('creator:id,name')->orderByDesc('id')->limit(15)->get()->map(fn ($payment) => [
                        'receipt' => $payment->receipt_number, 'amount' => $payment->amount,
                        'date' => $payment->payment_date?->toIso8601String(), 'notes' => $payment->notes,
                        'recorded_by' => $payment->creator?->name,
                    ])->all() : [],
            ] : null,
        ];
    }

    private function student(Student $student): array
    {
        return ['uuid' => $student->uuid, 'name' => $student->user?->name, 'nis' => $student->nis];
    }
}
