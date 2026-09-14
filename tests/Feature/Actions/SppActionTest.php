<?php

namespace Tests\Feature\Actions;

use App\Actions\Spp\RecordSppPaymentAction;
use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\SppBillStatus;
use App\Events\Spp\SppPaymentPosted;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class SppActionTest extends ActionTestCase
{
    public function test_partial_payment_updates_paid_amount_and_status(): void
    {
        Event::fake([SppPaymentPosted::class]);

        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $year = $this->createAcademicYear();
        $bill = $this->createSppBill($student, $year);

        $payment = app(RecordSppPaymentAction::class)->execute(
            actor: $actor,
            bill: $bill,
            amount: '200000.00',
            paymentMethod: PaymentMethod::Cash
        );

        $bill = $bill->fresh();

        $this->assertSame('200000.00', $bill->paid_amount);
        $this->assertSame(SppBillStatus::Partial, $bill->status);

        $this->assertDatabaseHas('spp_payments', [
            'id' => $payment->id,
            'amount' => '200000.00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $payment->id,
            'module' => 'spp',
            'action' => 'PAYMENT_POSTED',
        ]);

        Event::assertDispatched(
            SppPaymentPosted::class,
            fn (SppPaymentPosted $event): bool =>
                $event->sppPaymentId === $payment->id
        );
    }

    public function test_full_payment_marks_bill_paid(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $year = $this->createAcademicYear();
        $bill = $this->createSppBill($student, $year);

        $action = app(RecordSppPaymentAction::class);

        $action->execute(
            actor: $actor,
            bill: $bill,
            amount: '200000.00',
            paymentMethod: PaymentMethod::Cash
        );

        $action->execute(
            actor: $actor,
            bill: $bill,
            amount: '300000.00',
            paymentMethod: PaymentMethod::BankTransfer,
            referenceNumber: 'TRX-001'
        );

        $bill = $bill->fresh();

        $this->assertSame('500000.00', $bill->paid_amount);
        $this->assertSame(SppBillStatus::Paid, $bill->status);
        $this->assertSame(2, $bill->payments()->count());
    }

    public function test_overpayment_is_rejected_without_mutating_bill(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $year = $this->createAcademicYear();
        $bill = $this->createSppBill($student, $year);

        try {
            app(RecordSppPaymentAction::class)->execute(
                actor: $actor,
                bill: $bill,
                amount: '500000.01',
                paymentMethod: PaymentMethod::Cash
            );

            $this->fail('Overpayment should have failed.');
        } catch (ValidationException) {
            $bill = $bill->fresh();

            $this->assertSame('0.00', $bill->paid_amount);
            $this->assertSame(SppBillStatus::Overdue, $bill->status);
            $this->assertSame(0, $bill->payments()->count());
        }
    }

    public function test_cancelled_bill_rejects_payment(): void
    {
        $actor = $this->createActor('admin');
        [, $student] = $this->createStudentProfile();
        $year = $this->createAcademicYear();
        $bill = $this->createSppBill($student, $year);

        $bill->update([
            'status' => SppBillStatus::Cancelled,
        ]);

        $this->expectException(ValidationException::class);

        app(RecordSppPaymentAction::class)->execute(
            actor: $actor,
            bill: $bill,
            amount: '100000.00',
            paymentMethod: PaymentMethod::Cash
        );
    }
}
