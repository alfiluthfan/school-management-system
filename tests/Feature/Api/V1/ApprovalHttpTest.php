<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Finance\SavingTransactionStatus;
use App\Enums\System\ApprovalStatus;
use App\Models\System\Approval;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsHttpApiFixtures;
use Tests\TestCase;

class ApprovalHttpTest extends TestCase
{
    use BuildsHttpApiFixtures;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_direct_public_reversal_route_is_removed(): void
    {
        $this->assertFalse(
            Route::has(
                'api.v1.saving-transactions.reversal.store'
            )
        );
    }

    public function test_guest_gets_401_for_approval_index(): void
    {
        $this->getJson(
            route('api.v1.approvals.index')
        )->assertUnauthorized();
    }

    public function test_student_gets_403_for_approval_index(): void
    {
        [$studentUser] = $this->createApiStudent();

        $this->actingAs($studentUser)
            ->getJson(route('api.v1.approvals.index'))
            ->assertForbidden();
    }

    public function test_admin_can_submit_saving_reversal_approval_without_mutating_ledger(): void
    {
        [$admin, $transaction, $account] =
            $this->makePostedSavingTransaction();

        $response = $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-transactions'
                    .'.reversal-requests.store',
                    $transaction
                ),
                [
                    'reason' =>
                        'Deposit tercatat pada siswa yang salah.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.module.value',
                'SAVING'
            )
            ->assertJsonPath(
                'data.action.value',
                'VOID'
            )
            ->assertJsonPath(
                'data.status.value',
                'PENDING'
            );

        $approvalUuid = $response->json('data.uuid');

        $this->assertDatabaseHas('approvals', [
            'uuid' => $approvalUuid,
            'requested_by' => $admin->id,
            'status' => 'PENDING',
        ]);

        $this->assertSame(
            SavingTransactionStatus::Posted,
            $transaction->fresh()->status
        );

        $this->assertSame(
            '150000.00',
            $account->fresh()->current_balance
        );

        $this->assertSame(
            0,
            $transaction->reversals()->count()
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'approval',
            'action' => 'SUBMIT',
        ]);
    }

    public function test_duplicate_pending_reversal_request_is_rejected(): void
    {
        [$admin, $transaction] =
            $this->makePostedSavingTransaction();

        $url = route(
            'api.v1.saving-transactions'
            .'.reversal-requests.store',
            $transaction
        );

        $payload = [
            'reason' => 'Kesalahan input transaksi.',
        ];

        $this->actingAs($admin)
            ->postJson($url, $payload)
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson($url, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approval');

        $this->assertSame(
            1,
            Approval::query()->count()
        );
    }

    public function test_principal_can_list_pending_approval(): void
    {
        [$admin, $transaction] =
            $this->makePostedSavingTransaction();
        $principal = $this->createApiPrincipal();

        $submit = $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-transactions'
                    .'.reversal-requests.store',
                    $transaction
                ),
                [
                    'reason' => 'Kesalahan input transaksi.',
                ]
            )
            ->assertCreated();

        $uuid = $submit->json('data.uuid');

        $this->actingAs($principal)
            ->getJson(
                route(
                    'api.v1.approvals.index',
                    ['status' => 'PENDING']
                )
            )
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $uuid,
            ]);
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        [$admin, $transaction] =
            $this->makePostedSavingTransaction();

        $approval = $this->submitApproval(
            $admin,
            $transaction
        );

        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.approvals.approve',
                    $approval
                ),
                [
                    'review_notes' => 'Approve sendiri.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            ApprovalStatus::Pending,
            $approval->fresh()->status
        );
    }

    public function test_principal_approval_executes_reversal_once(): void
    {
        [$admin, $transaction, $account] =
            $this->makePostedSavingTransaction();

        $principal = $this->createApiPrincipal();

        $approval = $this->submitApproval(
            $admin,
            $transaction
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.approve',
                    $approval
                ),
                [
                    'review_notes' =>
                        'Bukti transaksi sudah diverifikasi.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'APPROVED'
            )
            ->assertJsonPath(
                'data.reviewer.uuid',
                $principal->uuid
            );

        $approval = $approval->fresh();

        $this->assertSame(
            ApprovalStatus::Approved,
            $approval->status
        );
        $this->assertSame(
            $principal->id,
            $approval->reviewed_by
        );

        $this->assertSame(
            SavingTransactionStatus::Reversed,
            $transaction->fresh()->status
        );

        $this->assertSame(
            '100000.00',
            $account->fresh()->current_balance
        );

        $this->assertSame(
            1,
            $transaction->reversals()->count()
        );

        $reversal = $transaction
            ->reversals()
            ->firstOrFail();

        $this->assertSame(
            $principal->id,
            $reversal->created_by
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $principal->id,
            'module' => 'approval',
            'action' => 'APPROVE',
            'entity_id' => $approval->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $principal->id,
            'module' => 'saving',
            'action' => 'REVERSAL',
            'entity_id' => $reversal->id,
        ]);
    }

    public function test_rejected_reversal_does_not_mutate_ledger_and_can_be_resubmitted(): void
    {
        [$admin, $transaction, $account] =
            $this->makePostedSavingTransaction();

        $principal = $this->createApiPrincipal();

        $approval = $this->submitApproval(
            $admin,
            $transaction
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.reject',
                    $approval
                ),
                [
                    'review_notes' =>
                        'Bukti koreksi belum mencukupi.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status.value',
                'REJECTED'
            );

        $this->assertSame(
            SavingTransactionStatus::Posted,
            $transaction->fresh()->status
        );

        $this->assertSame(
            '150000.00',
            $account->fresh()->current_balance
        );

        $this->assertSame(
            0,
            $transaction->reversals()->count()
        );

        /*
         * pending_key is released on rejection.
         */
        $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-transactions'
                    .'.reversal-requests.store',
                    $transaction
                ),
                [
                    'reason' =>
                        'Pengajuan ulang dengan bukti lengkap.',
                ]
            )
            ->assertCreated();

        $this->assertSame(
            2,
            Approval::query()->count()
        );
    }

    public function test_approved_request_cannot_execute_twice(): void
    {
        [$admin, $transaction] =
            $this->makePostedSavingTransaction();

        $principal = $this->createApiPrincipal();

        $approval = $this->submitApproval(
            $admin,
            $transaction
        );

        $url = route(
            'api.v1.approvals.approve',
            $approval
        );

        $this->actingAs($principal)
            ->postJson($url)
            ->assertOk();

        $this->actingAs($principal)
            ->postJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approval');

        $this->assertSame(
            1,
            $transaction->reversals()->count()
        );
    }

    public function test_principal_cannot_submit_saving_reversal_request(): void
    {
        [, $transaction] =
            $this->makePostedSavingTransaction();

        $principal = $this->createApiPrincipal();

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.saving-transactions'
                    .'.reversal-requests.store',
                    $transaction
                ),
                [
                    'reason' => 'Principal mencoba submit.',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_reject_requires_review_notes(): void
    {
        [$admin, $transaction] =
            $this->makePostedSavingTransaction();

        $principal = $this->createApiPrincipal();

        $approval = $this->submitApproval(
            $admin,
            $transaction
        );

        $this->actingAs($principal)
            ->postJson(
                route(
                    'api.v1.approvals.reject',
                    $approval
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'review_notes'
            );
    }

    /**
     * @return array{
     *   0: \App\Models\Auth\User,
     *   1: \App\Models\Finance\SavingTransaction,
     *   2: \App\Models\Finance\SavingAccount
     * }
     */
    private function makePostedSavingTransaction(): array
    {
        $admin = $this->createApiAdmin();
        [, $student] = $this->createApiStudent();

        $account = $this->createApiSavingAccount(
            $student,
            '100000.00'
        );

        $transaction = $this
            ->createApiSavingTransaction(
                $admin,
                $account,
                '50000.00'
            );

        return [
            $admin,
            $transaction,
            $account->fresh(),
        ];
    }

    private function submitApproval(
        $admin,
        $transaction
    ): Approval {
        $response = $this->actingAs($admin)
            ->postJson(
                route(
                    'api.v1.saving-transactions'
                    .'.reversal-requests.store',
                    $transaction
                ),
                [
                    'reason' =>
                        'Transaksi membutuhkan reversal.',
                ]
            )
            ->assertCreated();

        return Approval::query()
            ->where(
                'uuid',
                $response->json('data.uuid')
            )
            ->firstOrFail();
    }
}
