<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppBillStatus;
use App\Enums\Finance\SppPaymentStatus;
use App\Events\Spp\SppPaymentPosted;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Support\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class SppPaymentApprovalHttpTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 12:00:00');
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_submit_void_without_mutating_payment(): void
    {
        [$admin, $payment, $bill] = $this->makePostedPayment('200000.00');

        $response = $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.void-requests.store', $payment), [
                'reason' => 'Pembayaran dicatat pada transaksi yang salah.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.module.value', 'SPP')
            ->assertJsonPath('data.action.value', 'VOID')
            ->assertJsonPath('data.status.value', 'PENDING');

        $this->assertSame(SppPaymentStatus::Posted, $payment->fresh()->status);
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
        $this->assertDatabaseHas('approvals', [
            'uuid' => $response->json('data.uuid'),
            'status' => 'PENDING',
        ]);
    }

    public function test_principal_approval_voids_payment_and_recalculates_bill(): void
    {
        [$admin, $payment, $bill] = $this->makePostedPayment('200000.00');
        $principal = $this->createApiPrincipal();
        $approval = $this->submitVoid($admin, $payment);

        $this->actingAs($principal)
            ->postJson(route('api.v1.approvals.approve', $approval))
            ->assertOk();

        $payment = $payment->fresh();
        $bill = $bill->fresh();

        $this->assertSame(SppPaymentStatus::Void, $payment->status);
        $this->assertSame($principal->id, $payment->voided_by);
        $this->assertSame('0.00', $bill->paid_amount);
        $this->assertSame(SppBillStatus::Overdue, $bill->status);
    }

    public function test_rejected_void_does_not_mutate_payment(): void
    {
        [$admin, $payment, $bill] = $this->makePostedPayment('200000.00');
        $principal = $this->createApiPrincipal();
        $approval = $this->submitVoid($admin, $payment);

        $this->actingAs($principal)
            ->postJson(route('api.v1.approvals.reject', $approval), [
                'review_notes' => 'Bukti pembatalan belum cukup.',
            ])->assertOk();

        $this->assertSame(SppPaymentStatus::Posted, $payment->fresh()->status);
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
    }

    public function test_void_and_correction_cannot_both_be_pending(): void
    {
        [$admin, $payment] = $this->makePostedPayment('200000.00');

        $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.void-requests.store', $payment), [
                'reason' => 'Ajukan void terlebih dahulu.',
            ])->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.correction-requests.store', $payment), [
                'reason' => 'Mencoba koreksi payment yang sama.',
                'changes' => ['amount' => '150000.00'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approval');

        $this->assertSame(1, Approval::query()->count());
    }

    public function test_admin_can_submit_correction_without_mutating_payment(): void
    {
        [$admin, $payment, $bill] = $this->makePostedPayment('200000.00');

        $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.correction-requests.store', $payment), [
                'reason' => 'Nominal pembayaran salah input.',
                'changes' => [
                    'amount' => '150000.00',
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'reference_number' => 'BANK-CORRECTED-001',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.action.value', 'CORRECTION');

        $this->assertSame(SppPaymentStatus::Posted, $payment->fresh()->status);
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
    }

    public function test_principal_approval_creates_replacement_payment(): void
    {
        Event::fake([SppPaymentPosted::class]);

        [$admin, $payment, $bill] = $this->makePostedPayment('200000.00');
        $principal = $this->createApiPrincipal();
        $approval = $this->submitCorrection($admin, $payment, [
            'amount' => '150000.00',
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference_number' => 'BANK-CORRECTED-001',
            'notes' => 'Nominal sudah diverifikasi ulang.',
        ]);

        $this->actingAs($principal)
            ->postJson(route('api.v1.approvals.approve', $approval))
            ->assertOk()
            ->assertJsonPath('data.status.value', 'APPROVED');

        $original = $payment->fresh();
        $replacement = SppPayment::query()
            ->where('replaces_payment_id', $original->id)
            ->firstOrFail();

        $this->assertSame(SppPaymentStatus::Void, $original->status);
        $this->assertSame($principal->id, $original->voided_by);
        $this->assertSame(SppPaymentStatus::Posted, $replacement->status);
        $this->assertSame('150000.00', $replacement->amount);
        $this->assertSame(PaymentMethod::BankTransfer, $replacement->payment_method);
        $this->assertSame($admin->id, $replacement->created_by);
        $this->assertSame($original->id, $replacement->replaces_payment_id);

        $bill = $bill->fresh();
        $this->assertSame('150000.00', $bill->paid_amount);
        $this->assertSame(SppBillStatus::Partial, $bill->status);

        Event::assertDispatched(
            SppPaymentPosted::class,
            fn (SppPaymentPosted $event): bool => $event->sppPaymentId === $replacement->id
        );
    }

    public function test_correction_over_bill_total_is_rejected_before_pending_approval(): void
    {
        [$admin, $payment] = $this->makePostedPayment('200000.00');

        $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.correction-requests.store', $payment), [
                'reason' => 'Nominal salah input.',
                'changes' => ['amount' => '600000.00'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes.amount');

        $this->assertDatabaseCount('approvals', 0);
    }

    private function makePostedPayment(string $amount): array
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year, '500000.00');

        $payment = SppPayment::query()->create([
            'payment_number' => 'PAY-'.$this->httpToken('spp'),
            'receipt_number' => 'RCT-'.$this->httpToken('receipt'),
            'spp_bill_id' => $bill->id,
            'replaces_payment_id' => null,
            'created_by' => $admin->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash,
            'reference_number' => null,
            'payment_date' => now(),
            'status' => SppPaymentStatus::Posted,
            'notes' => null,
        ]);

        $bill->update([
            'paid_amount' => $amount,
            'status' => bccomp($amount, $bill->amount, 2) >= 0
                ? SppBillStatus::Paid
                : SppBillStatus::Partial,
        ]);

        return [$admin, $payment->fresh(), $bill->fresh()];
    }

    private function submitVoid($admin, SppPayment $payment): Approval
    {
        $response = $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.void-requests.store', $payment), [
                'reason' => 'Pembayaran perlu dibatalkan.',
            ])->assertCreated();

        return Approval::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    private function submitCorrection($admin, SppPayment $payment, array $changes): Approval
    {
        $response = $this->actingAs($admin)
            ->postJson(route('api.v1.spp-payments.correction-requests.store', $payment), [
                'reason' => 'Pembayaran perlu dikoreksi.',
                'changes' => $changes,
            ])->assertCreated();

        return Approval::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    }
}
