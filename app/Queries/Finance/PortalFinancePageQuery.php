<?php
namespace App\Queries\Finance;

use App\Authorization\SchoolDataScope;
use App\Enums\Finance\SavingAccountStatus;
use App\Enums\Finance\SppBillStatus;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final class PortalFinancePageQuery
{
    /** @param array<string,mixed> $filters */
    public function index(User $user, string $type, array $filters): array
    {
        $savingsAccess = Gate::forUser($user)->allows('viewAny', SavingAccount::class);
        $sppAccess = Gate::forUser($user)->allows('viewAny', SppBill::class);
        $tabs = [];
        if ($savingsAccess) $tabs[] = ['type' => 'savings', 'label' => 'Tabungan'];
        if ($sppAccess) $tabs[] = ['type' => 'spp', 'label' => 'SPP'];
        $query = $type === 'savings' ? SchoolDataScope::savingAccounts($user)
            : SchoolDataScope::sppBills($user);
        $statuses = $type === 'savings' ? SavingAccountStatus::cases() : SppBillStatus::cases();
        $allowed = array_map(static fn ($s) => $s->value, $statuses);
        $status = $filters['status'] ?? '';
        if ($status && ! in_array($status, $allowed, true)) {
            $status = ''; // Status of the other tab is never applied to this tab.
        }
        if ($status) $query->where('status', $status);
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $numberColumn = $type === 'savings' ? 'account_number' : 'bill_number';
            $query->where(function (Builder $q) use ($term, $numberColumn): void {
                $q->where($numberColumn, 'like', $term)
                    ->orWhereHas('student.user', fn (Builder $u) => $u->where('name', 'like', $term))
                    ->orWhereHas('student', fn (Builder $s) => $s->where('nis', 'like', $term));
            });
        }
        $dateColumn = $type === 'savings' ? 'opened_at' : 'due_date';
        if (! empty($filters['from'])) $query->whereDate($dateColumn, '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate($dateColumn, '<=', $filters['to']);
        // Snapshot is scoped and filtered identically to this list, before pagination.
        $summaryQuery = clone $query;
        $summary = $type === 'savings'
            ? [
                'title' => 'Total saldo dalam filter',
                'amount' => (string) (clone $summaryQuery)->sum('current_balance'),
            ]
            : [
                'title' => 'Sisa tagihan aktif dalam filter',
                'amount' => bcsub(
                    (string) (clone $summaryQuery)->where('status', '!=', 'CANCELLED')->sum('amount'),
                    (string) (clone $summaryQuery)->where('status', '!=', 'CANCELLED')->sum('paid_amount'),
                    2
                ),
            ];
        $records = $query->with('student.user')->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
        return [
            'type' => $type, 'tabs' => $tabs, 'summary' => $summary,
            'cashier_available' => $user->hasPermission('student.view.all') && (
                $user->hasPermission('saving.deposit.create') || $user->hasPermission('saving.withdrawal.create')
                || $user->hasPermission('spp.payment.create')), 
            'filters' => [
                'search' => $filters['search'] ?? '', 'status' => $status,
                'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'statuses' => array_map(static fn ($item) => [
                'value' => $item->value, 'label' => $item->label(),
            ], $statuses),
            'records' => $this->paginated($records, fn ($r) => $type === 'savings'
                ? $this->account($r) : $this->bill($r)),
        ];
    }

    public function account(SavingAccount $account): array
    {
        return [
            'uuid' => $account->uuid, 'number' => $account->account_number,
            'student' => $this->student($account->student),
            'balance' => $account->current_balance,
            'status' => ['value' => $account->status->value, 'label' => $account->status->label()],
            'opened_at' => $account->opened_at?->toIso8601String(),
        ];
    }

    public function bill(SppBill $bill): array
    {
        return [
            'uuid' => $bill->uuid, 'number' => $bill->bill_number,
            'student' => $this->student($bill->student),
            'period' => ['month' => $bill->billing_month, 'year' => $bill->billing_year],
            'amount' => $bill->amount, 'paid_amount' => $bill->paid_amount,
            'outstanding' => bcsub($bill->amount, $bill->paid_amount, 2),
            'due_date' => $bill->due_date?->toDateString(),
            'status' => ['value' => $bill->status->value, 'label' => $bill->status->label()],
        ];
    }

    public function accountDetail(User $user, SavingAccount $account): array
    {
        $account->load('student.user');
        $canReadHistory = Gate::forUser($user)->allows('viewAny', SavingTransaction::class);
        $history = $canReadHistory ? SchoolDataScope::savingTransactions($user)
            ->where('saving_account_id', $account->id)->with('creator:id,name')
            ->orderByDesc('transaction_date')->orderByDesc('id')->paginate(15)->withQueryString() : null;
        return [
            'account' => $this->account($account),
            'transactions' => $history ? $this->paginated($history, fn (SavingTransaction $t) => [
                'uuid' => $t->uuid, 'number' => $t->transaction_number,
                'type' => ['value' => $t->transaction_type->value, 'label' => $t->transaction_type->label()],
                'amount' => $t->amount, 'balance_after' => $t->balance_after,
                'date' => $t->transaction_date?->toIso8601String(), 'description' => $t->description,
                'recorded_by' => $t->creator?->name,
                'can_request_reversal' => Gate::forUser($user)->allows('void', $t)
                    && Gate::forUser($user)->allows('create', Approval::class)
                    && $t->status->value === 'POSTED' && $t->transaction_type->value !== 'REVERSAL',
            ]) : null,
            'can' => [
                'deposit' => Gate::forUser($user)->allows('view', $account)
                    && Gate::forUser($user)->allows('deposit', $account)
                    && $account->status === SavingAccountStatus::Active,
                'withdraw' => Gate::forUser($user)->allows('view', $account)
                    && Gate::forUser($user)->allows('withdraw', $account)
                    && $account->status === SavingAccountStatus::Active,
            ],
        ];
    }

    public function billDetail(User $user, SppBill $bill): array
    {
        $bill->load('student.user');
        $canReadHistory = Gate::forUser($user)->allows('viewAny', SppPayment::class);
        $payments = $canReadHistory ? SchoolDataScope::sppPayments($user)
            ->where('spp_bill_id', $bill->id)->with('creator:id,name')->orderByDesc('payment_date')
            ->orderByDesc('id')->paginate(15)->withQueryString() : null;
        return [
            'bill' => $this->bill($bill),
            'payments' => $payments ? $this->paginated($payments, fn (SppPayment $p) => [
                'uuid' => $p->uuid, 'number' => $p->payment_number,
                'receipt_number' => $p->receipt_number, 'amount' => $p->amount,
                'method' => ['value' => $p->payment_method->value, 'label' => $p->payment_method->label()],
                'status' => ['value' => $p->status->value, 'label' => $p->status->label()],
                'date' => $p->payment_date?->toIso8601String(), 'notes' => $p->notes,
                'recorded_by' => $p->creator?->name,
                'can_request_void' => Gate::forUser($user)->allows('void', $p)
                    && Gate::forUser($user)->allows('create', Approval::class)
                    && $p->status->value === 'POSTED',
            ]) : null,
            'methods' => array_map(static fn ($m) => ['value' => $m->value, 'label' => $m->label()],
                \App\Enums\Finance\PaymentMethod::cases()),
            'can' => ['pay' => Gate::forUser($user)->allows('create', SppPayment::class)
                && ! in_array($bill->status, [SppBillStatus::Paid, SppBillStatus::Cancelled], true)
                && bccomp($bill->amount, $bill->paid_amount, 2) > 0],
        ];
    }

    private function student($student): array
    {
        return [
            'uuid' => $student->uuid, 'nis' => $student->nis,
            'name' => $student->user?->name,
        ];
    }

    private function paginated($paginator, callable $transform): array
    {
        return [
            'data' => $paginator->getCollection()->map($transform)->values()->all(),
            'total' => $paginator->total(), 'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(), 'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(), 'previous' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}
