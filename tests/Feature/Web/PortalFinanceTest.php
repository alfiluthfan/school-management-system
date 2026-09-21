<?php
namespace Tests\Feature\Web;

use App\Models\Auth\User;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalFinanceTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function inertiaGet(string $url)
    {
        $initial = $this->get($url)->assertOk()->assertViewHas('page');
        $version = data_get($initial->viewData('page'), 'version');
        return $this->get($url, [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '',
        ]);
    }

    public function test_guest_redirects_and_teacher_without_finance_permissions_is_denied(): void
    {
        $this->get('/finance')->assertRedirect('/login');
        [$teacher] = $this->createApiTeacher();
        $this->actingAs($teacher, 'web')->get('/finance')->assertForbidden();
    }

    public function test_student_sees_only_own_savings_and_no_other_account_detail(): void
    {
        [$user, $student] = $this->createApiStudent();
        [, $other] = $this->createApiStudent();
        $own = $this->createApiSavingAccount($student);
        $foreign = $this->createApiSavingAccount($other);
        $this->actingAs($user, 'web');
        $this->inertiaGet('/finance?type=savings')->assertOk()
            ->assertJsonPath('component', 'Finance/Index')
            ->assertJsonPath('props.finance.records.total', 1)
            ->assertJsonPath('props.finance.records.data.0.uuid', $own->uuid)
            ->assertDontSee($foreign->uuid);
        $this->get('/finance/savings/'.$foreign->uuid)->assertNotFound();
        $this->inertiaGet('/finance/savings/'.$own->uuid)->assertOk()
            ->assertJsonPath('props.finance.can.deposit', false)
            ->assertJsonPath('props.finance.can.withdraw', false);
    }

    public function test_parent_only_sees_linked_child_bill_and_cannot_pay(): void
    {
        [$parent, $guardian] = $this->createApiParent();
        [, $child] = $this->createApiStudent();
        [, $foreign] = $this->createApiStudent();
        $this->linkApiParentToStudent($guardian, $child);
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($child, $year);
        $other = $this->createApiSppBill($foreign, $year);
        $this->actingAs($parent, 'web');
        $this->inertiaGet('/finance?type=spp')->assertOk()
            ->assertJsonPath('props.finance.records.total', 1)
            ->assertJsonPath('props.finance.records.data.0.uuid', $bill->uuid)
            ->assertDontSee($other->uuid);
        $this->get('/finance/spp/'.$other->uuid)->assertNotFound();
        $this->inertiaGet('/finance/spp/'.$bill->uuid)->assertOk()
            ->assertJsonPath('props.finance.can.pay', false);
    }

    public function test_admin_deposit_and_withdraw_reuse_domain_actions(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student, '100000.00');
        $this->actingAs($admin, 'web');
        $this->inertiaGet('/finance/savings/'.$account->uuid)->assertOk()
            ->assertJsonPath('props.finance.can.deposit', true)
            ->assertJsonPath('props.finance.can.withdraw', true);
        $this->post('/finance/savings/'.$account->uuid.'/deposit', [
            'amount' => '50000.00', 'description' => 'Setoran portal',
        ])->assertRedirect('/finance/savings/'.$account->uuid)
            ->assertSessionHas('success');
        $this->assertSame('150000.00', $account->fresh()->current_balance);
        $this->post('/finance/savings/'.$account->uuid.'/withdraw', [
            'amount' => '25000.00',
        ])->assertRedirect('/finance/savings/'.$account->uuid)
            ->assertSessionHas('success');
        $this->assertSame('125000.00', $account->fresh()->current_balance);
        $this->assertDatabaseCount('saving_transactions', 2);
    }

    public function test_withdrawal_with_insufficient_balance_does_not_mutate_account(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student, '100000.00');
        $this->actingAs($admin, 'web')->from('/finance/savings/'.$account->uuid)
            ->post('/finance/savings/'.$account->uuid.'/withdraw', ['amount' => '100000.01'])
            ->assertRedirect('/finance/savings/'.$account->uuid)->assertSessionHasErrors('amount');
        $this->assertSame('100000.00', $account->fresh()->current_balance);
        $this->assertDatabaseCount('saving_transactions', 0);
    }

    public function test_principal_cannot_deposit_or_record_payment(): void
    {
        $principal = $this->createApiPrincipal();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $account = $this->createApiSavingAccount($student);
        $bill = $this->createApiSppBill($student, $year);
        $this->actingAs($principal, 'web')
            ->post('/finance/savings/'.$account->uuid.'/deposit', ['amount' => '20000.00'])
            ->assertForbidden();
        $this->post('/finance/spp/'.$bill->uuid.'/payments', [
            'amount' => '10000.00', 'payment_method' => 'CASH',
        ])->assertForbidden();
    }

    public function test_admin_payment_uses_existing_action_and_prevents_overpayment(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $bill = $this->createApiSppBill($student, $this->createApiAcademicYear());
        $this->actingAs($admin, 'web');
        $this->inertiaGet('/finance/spp/'.$bill->uuid)->assertOk()
            ->assertJsonPath('props.finance.can.pay', true);
        $this->post('/finance/spp/'.$bill->uuid.'/payments', [
            'amount' => '200000.00', 'payment_method' => 'CASH',
        ])->assertRedirect('/finance/spp/'.$bill->uuid)->assertSessionHas('success');
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
        $this->from('/finance/spp/'.$bill->uuid)->post('/finance/spp/'.$bill->uuid.'/payments', [
            'amount' => '300000.01', 'payment_method' => 'CASH',
        ])->assertSessionHasErrors('amount');
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
        $this->assertDatabaseCount('spp_payments', 1);
    }

    public function test_invalid_filter_is_rejected_and_finance_payload_hides_internal_ids(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student);
        $this->actingAs($admin, 'web');
        $this->get('/finance?type=savings&status=HACKED')->assertSessionHasErrors('status');
        $this->inertiaGet('/finance?type=savings')->assertOk()
            ->assertJsonPath('props.finance.records.data.0.uuid', $account->uuid)
            ->assertJsonMissingPath('props.finance.records.data.0.id')
            ->assertJsonMissingPath('props.finance.records.data.0.student.id');
    }

    public function test_admin_reversal_request_creates_approval_without_changing_balance(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student, '100000.00');
        $transaction = $this->createApiSavingTransaction($admin, $account, '30000.00');
        $before = $account->fresh()->current_balance;
        $this->actingAs($admin, 'web');
        $this->post('/finance/savings/'.$account->uuid.'/transactions/'.$transaction->uuid.'/reversal-requests', [
            'reason' => 'Nominal transaksi perlu dibatalkan.',
        ])->assertRedirect('/finance/savings/'.$account->uuid)
            ->assertSessionHas('success');
        $this->assertSame($before, $account->fresh()->current_balance);
        $this->assertDatabaseCount('approvals', 1);
        $this->assertDatabaseCount('saving_transactions', 1);
    }

    public function test_admin_payment_void_request_does_not_change_bill_until_approved(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $bill = $this->createApiSppBill($student, $this->createApiAcademicYear());
        $this->actingAs($admin, 'web');
        $this->post('/finance/spp/'.$bill->uuid.'/payments', [
            'amount' => '100000.00', 'payment_method' => 'CASH',
        ])->assertRedirect('/finance/spp/'.$bill->uuid);
        $payment = SppPayment::query()->firstOrFail();
        $this->post('/finance/spp/'.$bill->uuid.'/payments/'.$payment->uuid.'/void-requests', [
            'reason' => 'Nominal perlu dibatalkan dan diperiksa.',
        ])->assertRedirect('/finance/spp/'.$bill->uuid)->assertSessionHas('success');
        $this->assertDatabaseCount('approvals', 1);
        $this->assertSame('100000.00', $bill->fresh()->paid_amount);
        $this->assertSame('POSTED', $payment->fresh()->status->value);
    }

    public function test_payment_void_rejects_unrelated_bill(): void
    {
        $admin = $this->createApiAdmin();
        [, $studentA] = $this->createApiStudent();
        [, $studentB] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $billA = $this->createApiSppBill($studentA, $year);
        $billB = $this->createApiSppBill($studentB, $year);
        $this->actingAs($admin, 'web')->post('/finance/spp/'.$billB->uuid.'/payments', [
            'amount' => '100000.00', 'payment_method' => 'CASH',
        ])->assertRedirect('/finance/spp/'.$billB->uuid);
        $paymentB = SppPayment::query()->firstOrFail();
        $this->post('/finance/spp/'.$billA->uuid.'/payments/'.$paymentB->uuid.'/void-requests', [
            'reason' => 'Pengujian relasi tagihan dan pembayaran.',
        ])->assertNotFound();
        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_reversal_route_rejects_transaction_from_a_different_account(): void
    {
        $admin = $this->createApiAdmin();
        [, $studentA] = $this->createApiStudent();
        [, $studentB] = $this->createApiStudent();
        $accountA = $this->createApiSavingAccount($studentA);
        $accountB = $this->createApiSavingAccount($studentB);
        $transactionB = $this->createApiSavingTransaction($admin, $accountB);
        $this->actingAs($admin, 'web')->post('/finance/savings/'.$accountA->uuid.'/transactions/'.$transactionB->uuid.'/reversal-requests', [
            'reason' => 'Uji kepemilikan relasi transaksi.',
        ])->assertNotFound();
        $this->assertDatabaseCount('approvals', 0);
    }
}
