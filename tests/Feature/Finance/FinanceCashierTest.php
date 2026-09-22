<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use Illuminate\Support\Str;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class FinanceCashierTest extends HttpApiTestCase
{
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }

    private function body($student, string $operation = 'DEPOSIT', string $amount = '10000.00'): array
    {
        return ['student_uuid' => $student->uuid, 'request_key' => (string) Str::uuid(),
            'operation' => $operation, 'amount' => $amount,
            'description' => $operation === 'WITHDRAW' ? 'Untuk keperluan buku' : 'Setoran tunai'];
    }

    public function test_cashier_requires_login_and_operational_permissions(): void
    {
        $this->get('/finance/cashier')->assertRedirect('/login');
        [$studentUser, $student] = $this->createApiStudent();
        $this->actingAs($studentUser, 'web')->get('/finance/cashier?search=student')->assertForbidden();
        $this->post('/finance/cashier/savings', $this->body($student))->assertForbidden();
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web')->get('/finance/cashier')->assertForbidden();
        $this->post('/finance/cashier/spp', ['student_uuid' => $student->uuid,
            'request_key' => (string) Str::uuid(), 'bill_uuid' => (string) Str::uuid(), 'amount' => '100.00'])->assertForbidden();
    }

    public function test_admin_finds_students_but_cannot_enumerate_without_search(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $this->actingAs($admin, 'web')->get('/finance/cashier')->assertOk();
        $this->get('/finance/cashier?student='.$student->uuid)->assertOk();
        $this->get('/finance/cashier?search=a')->assertOk();
        $this->get('/finance/cashier?student='.Str::uuid())->assertNotFound();
    }

    public function test_first_deposit_creates_zero_opening_account_and_transaction(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $this->actingAs($admin, 'web')->post('/finance/cashier/savings', $this->body($student))
            ->assertRedirect('/finance/cashier?student='.$student->uuid)->assertSessionHas('success');
        $account = SavingAccount::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('10000.00', $account->current_balance);
        $transaction = SavingTransaction::query()->firstOrFail();
        $this->assertSame('0.00', $transaction->balance_before);
        $this->assertSame($admin->id, $transaction->created_by);
        $this->assertNotNull($transaction->transaction_date);
    }

    public function test_retry_same_request_key_creates_no_second_deposit(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $data = $this->body($student);
        $this->actingAs($admin, 'web')->post('/finance/cashier/savings', $data)->assertSessionHas('success');
        $this->post('/finance/cashier/savings', $data)->assertSessionHas('success');
        $this->assertDatabaseCount('saving_transactions', 1);
        $data['amount'] = '999999.00';
        $this->from('/finance/cashier')->post('/finance/cashier/savings', $data)->assertSessionHasErrors('request_key');
        $this->assertSame('10000.00', SavingAccount::query()->firstOrFail()->current_balance);
    }

    public function test_withdraw_requires_reason_and_refuses_overdraft(): void
    {
        $admin = $this->createApiAdmin(); [, $student] = $this->createApiStudent();
        $this->createApiSavingAccount($student, '10000.00');
        $data = $this->body($student, 'WITHDRAW', '20000.00');
        $this->actingAs($admin, 'web')->from('/finance/cashier')
            ->post('/finance/cashier/savings', $data)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('saving_transactions', 0);
        $data['amount'] = '5000.00'; $data['description'] = '';
        $this->post('/finance/cashier/savings', $data)->assertSessionHasErrors('description');
        $data['description'] = 'Pembelian buku';
        $this->post('/finance/cashier/savings', $data)->assertSessionHas('success');
        $this->assertSame('5000.00', SavingAccount::query()->firstOrFail()->current_balance);
    }

    public function test_student_and_linked_parent_see_transactions_read_only(): void
    {
        $admin = $this->createApiAdmin();
        [$studentUser, $student] = $this->createApiStudent();
        [$parentUser, $guardian] = $this->createApiParent();
        $this->linkApiParentToStudent($guardian, $student);
        $this->actingAs($admin, 'web')->post('/finance/cashier/savings', $this->body($student))
            ->assertSessionHas('success');
        $account = SavingAccount::query()->firstOrFail();
        foreach ([$studentUser, $parentUser] as $viewer) {
            $this->actingAs($viewer, 'web');
            $this->get('/finance/cashier')->assertForbidden();
            $this->get('/finance/savings/'.$account->uuid)->assertOk();
            $this->post('/finance/cashier/savings', $this->body($student))->assertForbidden();
        }
        $this->assertDatabaseCount('saving_transactions', 1);
    }

    public function test_spp_cash_payment_reuses_domain_action_and_rejects_foreign_bill(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent(); [, $foreign] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year); $other = $this->createApiSppBill($foreign, $year);
        $data = ['student_uuid' => $student->uuid, 'request_key' => (string) Str::uuid(),
            'bill_uuid' => $other->uuid, 'amount' => '150000.00', 'notes' => 'Cash sekolah'];
        $this->actingAs($admin, 'web')->post('/finance/cashier/spp', $data)->assertNotFound();
        $data['bill_uuid'] = $bill->uuid;
        $this->post('/finance/cashier/spp', $data)->assertSessionHas('success');
        $this->assertSame('150000.00', $bill->fresh()->paid_amount);
        $payment = SppPayment::query()->firstOrFail();
        $this->assertSame('CASH', $payment->payment_method->value);
        $this->assertSame($admin->id, $payment->created_by);
        $this->post('/finance/cashier/spp', $data)->assertSessionHas('success');
        $this->assertDatabaseCount('spp_payments', 1);
    }
}
