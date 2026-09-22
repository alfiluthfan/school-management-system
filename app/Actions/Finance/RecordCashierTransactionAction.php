<?php

namespace App\Actions\Finance;

use App\Actions\Savings\DepositSavingAction;
use App\Actions\Savings\WithdrawSavingAction;
use App\Actions\Spp\RecordSppPaymentAction;
use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SavingAccountStatus;
use App\Models\Academic\Student;
use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Queries\Finance\FinanceCashierQuery;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RecordCashierTransactionAction
{
    public function __construct(
        private readonly DepositSavingAction $deposit,
        private readonly WithdrawSavingAction $withdraw,
        private readonly RecordSppPaymentAction $pay,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string,mixed> $data @return array{uuid:string,duplicate:bool} */
    public function execute(User $actor, array $data, string $kind): array
    {
        FinanceCashierQuery::authorize($actor);
        $operation = $kind === 'savings' ? $data['operation'] : 'SPP';
        $payloadHash = hash('sha256', json_encode([
            $data['student_uuid'], $operation, $data['bill_uuid'] ?? null,
            $data['amount'], $data['description'] ?? null, $data['notes'] ?? null,
        ], JSON_THROW_ON_ERROR));
        abort_unless(in_array($operation, ['DEPOSIT', 'WITHDRAW', 'SPP'], true), 422);
        abort_unless($kind === 'savings' ? $actor->hasPermission('saving.balance.view.all') && $actor->hasPermission($operation === 'DEPOSIT'
            ? 'saving.deposit.create' : 'saving.withdrawal.create') : ($actor->hasPermission('spp.bill.view.all') && Gate::forUser($actor)->allows('create', SppPayment::class)), 403);

        return DB::transaction(function () use ($actor, $data, $operation, $payloadHash): array {
            // Serialize submissions by the same actor. Combined with a unique key, retries are idempotent.
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('cashier_requests')->where('actor_id', $actor->id)
                ->where('operation', $operation)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->student_uuid !== $data['student_uuid'] || $existing->payload_hash !== $payloadHash) {
                    throw ValidationException::withMessages(['request_key' => 'Kunci transaksi telah digunakan untuk siswa lain.']);
                }
                return ['uuid' => $existing->result_uuid, 'duplicate' => true];
            }
            $student = Student::query()->where('uuid', $data['student_uuid'])
                ->lockForUpdate()->firstOrFail();
            if ($student->getRawOriginal('status') !== 'ACTIVE') {
                throw ValidationException::withMessages(['student_uuid' => 'Siswa tidak aktif.']);
            }
            if ($operation === 'SPP') {
                $bill = SppBill::query()->where('uuid', $data['bill_uuid'])
                    ->where('student_id', $student->id)->firstOrFail();
                Gate::forUser($actor)->authorize('view', $bill);
                $transaction = $this->pay->execute($actor, $bill, (string) $data['amount'],
                    PaymentMethod::Cash, null, $data['notes'] ?? null);
            } else {
                $account = SavingAccount::query()->where('student_id', $student->id)->first();
                if (! $account && $operation === 'DEPOSIT') {
                    $account = SavingAccount::query()->create([
                        'student_id' => $student->id,
                        'account_number' => 'SAV-STU-'.$student->id,
                        'current_balance' => '0.00',
                        'status' => SavingAccountStatus::Active,
                        'opened_at' => now(), 'closed_at' => null,
                    ]);
                    $this->auditLogger->log($actor, 'saving', 'ACCOUNT_OPENED', $account, null,
                        $account->getAttributes(), ['student_id' => $student->id]);
                }
                if (! $account) {
                    throw ValidationException::withMessages(['student_uuid' => 'Siswa belum mempunyai rekening tabungan.']);
                }
                Gate::forUser($actor)->authorize('view', $account);
                Gate::forUser($actor)->authorize($operation === 'DEPOSIT' ? 'deposit' : 'withdraw', $account);
                if ($account->getRawOriginal('status') !== SavingAccountStatus::Active->value) {
                    throw ValidationException::withMessages(['student_uuid' => 'Rekening tabungan tidak aktif.']);
                }
                $transaction = $operation === 'DEPOSIT'
                    ? $this->deposit->execute($actor, $account, (string) $data['amount'], $data['description'] ?? null)
                    : $this->withdraw->execute($actor, $account, (string) $data['amount'], $data['description']);
            }
            DB::table('cashier_requests')->insert([
                'actor_id' => $actor->id, 'operation' => $operation,
                'request_key' => $data['request_key'], 'student_uuid' => $student->uuid,
                'result_uuid' => $transaction->uuid, 'payload_hash' => $payloadHash, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return ['uuid' => $transaction->uuid, 'duplicate' => false];
        }, 3);
    }
}
