<?php

namespace Tests\Feature\Api\V1;

class SavingsHttpTest extends HttpApiTestCase
{
    public function test_unauthenticated_user_gets_401_for_saving_accounts(): void
    {
        $this->getJson(route('api.v1.saving-accounts.index'))
            ->assertUnauthorized();
    }

    public function test_admin_can_create_deposit_and_receives_201(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount(
            $student,
            '100000.00'
        );

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-accounts.deposits.store',
                    $account
                ),
                [
                    'amount' => '50000.00',
                    'description' => 'HTTP deposit',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.type.value', 'DEPOSIT')
            ->assertJsonPath('data.balance_after', '150000.00');

        $this->assertSame(
            '150000.00',
            $account->fresh()->current_balance
        );
    }

    public function test_admin_can_withdraw_and_receives_201(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount(
            $student,
            '100000.00'
        );

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-accounts.withdrawals.store',
                    $account
                ),
                [
                    'amount' => '25000.00',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.type.value', 'WITHDRAWAL')
            ->assertJsonPath('data.balance_after', '75000.00');
    }

    public function test_invalid_deposit_amount_returns_422(): void
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student);

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-accounts.deposits.store',
                    $account
                ),
                [
                    'amount' => '0',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_principal_cannot_create_deposit(): void
    {
        $principal = $this->createApiPrincipal();
        [, $student] = $this->createApiStudent();
        $account = $this->createApiSavingAccount($student);

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.saving-accounts.deposits.store',
                    $account
                ),
                [
                    'amount' => '50000.00',
                ]
            )
            ->assertForbidden();
    }

    public function test_student_gets_404_for_another_students_saving_account(): void
    {
        [$user] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();
        $otherAccount = $this->createApiSavingAccount($otherStudent);

        $this->actingAs($user)
            ->getJson(
                route(
                    'api.v1.saving-accounts.show',
                    $otherAccount
                )
            )
            ->assertNotFound();
    }

    public function test_student_saving_account_index_does_not_leak_other_accounts(): void
    {
        [$user, $student] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $ownAccount = $this->createApiSavingAccount($student);
        $otherAccount = $this->createApiSavingAccount($otherStudent);

        $this->actingAs($user)
            ->getJson(route('api.v1.saving-accounts.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $ownAccount->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherAccount->uuid,
            ]);
    }

    public function test_parent_saving_account_index_only_contains_linked_child(): void
    {
        [$parentUser, $guardian] = $this->createApiParent();
        [, $child] = $this->createApiStudent();
        [, $otherStudent] = $this->createApiStudent();

        $this->linkApiParentToStudent($guardian, $child);

        $childAccount = $this->createApiSavingAccount($child);
        $otherAccount = $this->createApiSavingAccount($otherStudent);

        $this->actingAs($parentUser)
            ->getJson(route('api.v1.saving-accounts.index'))
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $childAccount->uuid,
            ])
            ->assertJsonMissing([
                'uuid' => $otherAccount->uuid,
            ]);
    }

    public function test_teacher_cannot_open_saving_account_index(): void
    {
        [$teacherUser] = $this->createApiTeacher();

        $this->actingAs($teacherUser)
            ->getJson(route('api.v1.saving-accounts.index'))
            ->assertForbidden();
    }

    // public function test_admin_can_reverse_posted_transaction_and_receives_201(): void
    // {
    //     $admin = $this->createApiAdmin();
    //     [, $student] = $this->createApiStudent();
    //     $account = $this->createApiSavingAccount(
    //         $student,
    //         '100000.00'
    //     );
    //     $transaction = $this->createApiSavingTransaction(
    //         $admin,
    //         $account,
    //         '50000.00'
    //     );

    //     $this->actingAs($admin)
    //         ->postJson(
    //             route(
    //                 'api.v1.saving-transactions.reversal.store',
    //                 $transaction
    //             ),
    //             [
    //                 'reason' => 'Wrong HTTP transaction amount',
    //             ]
    //         )
    //         ->assertCreated()
    //         ->assertJsonPath('data.type.value', 'REVERSAL');

    //     $this->assertSame(
    //         '100000.00',
    //         $account->fresh()->current_balance
    //     );
    // }
}
