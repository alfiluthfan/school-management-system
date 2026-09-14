<?php

namespace Tests\Feature\Api\V1;

class SppHttpTest extends HttpApiTestCase
{
    public function test_unauthenticated_user_gets_401_for_spp_bills(): void
    {
        $this->getJson(route('api.v1.spp-bills.index'))
            ->assertUnauthorized();
    }

    public function test_invalid_payment_method_returns_422(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year);

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.spp-bills.payments.store',
                    $bill
                ),
                [
                    'amount' => '100000.00',
                    'payment_method' => 'CRYPTO',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_admin_can_record_spp_payment_and_receives_201(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year);

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.spp-bills.payments.store',
                    $bill
                ),
                [
                    'amount' => '200000.00',
                    'payment_method' => 'CASH',
                    'notes' => 'HTTP partial payment',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.amount', '200000.00')
            ->assertJsonPath('data.status.value', 'POSTED');

        $bill = $bill->fresh();

        $this->assertSame('200000.00', $bill->paid_amount);
        $this->assertSame('PARTIAL', $bill->status->value);
    }

    public function test_principal_cannot_record_spp_payment(): void
    {
        $principal = $this->createApiPrincipal();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year);

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.spp-bills.payments.store',
                    $bill
                ),
                [
                    'amount' => '100000.00',
                    'payment_method' => 'CASH',
                ]
            )
            ->assertForbidden();
    }

    public function test_overpayment_returns_422_and_does_not_mutate_bill(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $bill = $this->createApiSppBill($student, $year);

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.spp-bills.payments.store',
                    $bill
                ),
                [
                    'amount' => '500000.01',
                    'payment_method' => 'CASH',
                ]
            )
            ->assertUnprocessable();

        $bill = $bill->fresh();

        $this->assertSame('0.00', $bill->paid_amount);
        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_student_gets_404_for_another_students_spp_bill(): void
    {
        [$user] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $year = $this->createApiAcademicYear();
        $otherBill = $this->createApiSppBill(
            $otherStudent,
            $year
        );

        $this->actingAs($user)
            ->getJson(
                route(
                    'api.v1.spp-bills.show',
                    $otherBill
                )
            )
            ->assertNotFound();
    }

    public function test_student_spp_bill_index_does_not_leak_other_bills(): void
    {
        [$user, $student] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $year = $this->createApiAcademicYear();

        $ownBill = $this->createApiSppBill(
            $student,
            $year,
            '500000.00',
            9
        );
        $otherBill = $this->createApiSppBill(
            $otherStudent,
            $year,
            '500000.00',
            10
        );

        $this->actingAs($user)
            ->getJson(route('api.v1.spp-bills.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $ownBill->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherBill->uuid,
            ]);
    }

    public function test_parent_spp_bill_index_only_contains_linked_child(): void
    {
        [$parentUser, $guardian] = $this->createApiParent();
        [, $child] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $this->linkApiParentToStudent($guardian, $child);

        $year = $this->createApiAcademicYear();

        $childBill = $this->createApiSppBill(
            $child,
            $year,
            '500000.00',
            9
        );
        $otherBill = $this->createApiSppBill(
            $otherStudent,
            $year,
            '500000.00',
            10
        );

        $this->actingAs($parentUser)
            ->getJson(route('api.v1.spp-bills.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $childBill->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherBill->uuid,
            ]);
    }

    public function test_teacher_cannot_open_spp_bill_index(): void
    {
        [$teacherUser] = $this->createApiTeacher();

        $this->actingAs($teacherUser)
            ->getJson(route('api.v1.spp-bills.index'))
            ->assertForbidden();
    }
}
