<?php

namespace App\Queries\Dashboard;

use App\Authorization\SchoolDataScope;
use App\Models\Auth\User;
use App\Models\System\Approval;
use App\Models\System\AuditLog;
use App\Models\Communication\NotificationLog;
use Carbon\CarbonImmutable;

final class DashboardOverviewQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        CarbonImmutable $date
    ): array {
        $result = [
            'date' => $date->toDateString(),
        ];

        if (
            $user->hasAnyPermission([
                'student-attendance.view.all',
                'student-attendance.view.class',
                'student-attendance.view.own',
                'student-attendance.view.child',
            ])
        ) {
            $result['student_attendance'] =
                $this->studentAttendance(
                    $user,
                    $date
                );
        }

        if (
            $user->hasAnyPermission([
                'teacher-attendance.view.all',
                'teacher-attendance.view.own',
            ])
        ) {
            $result['teacher_attendance'] =
                $this->teacherAttendance(
                    $user,
                    $date
                );
        }

        if (
            $user->hasAnyPermission([
                'saving.balance.view.all',
                'saving.balance.view.own',
                'saving.balance.view.child',
            ])
        ) {
            $result['savings'] =
                $this->savings($user);
        }

        if (
            $user->hasAnyPermission([
                'spp.bill.view.all',
                'spp.bill.view.own',
                'spp.bill.view.child',
            ])
        ) {
            $result['spp'] =
                $this->spp($user);
        }

        if (
            $user->hasAnyPermission([
                'approval.view.all',
                'approval.view.own',
            ])
        ) {
            $result['approvals'] =
                $this->approvals($user);
        }

        if (
            $user->hasPermission(
                'notification.view.logs'
            )
        ) {
            $result['notifications'] =
                $this->notifications();
        }

        if (
            $user->hasPermission(
                'audit.view'
            )
        ) {
            $result['activity'] = [
                'recent' =>
                    $this->recentActivity(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function studentAttendance(
        User $user,
        CarbonImmutable $date
    ): array {
        $query = SchoolDataScope
            ::studentAttendances($user)
            ->whereDate(
                'attendance_date',
                $date->toDateString()
            );

        $total = (clone $query)->count();

        $counts = (clone $query)
            ->selectRaw(
                'status, COUNT(*) AS aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        return [
            'total' => $total,
            'present' => (int) (
                $counts['PRESENT'] ?? 0
            ),
            'late' => (int) (
                $counts['LATE'] ?? 0
            ),
            'sick' => (int) (
                $counts['SICK'] ?? 0
            ),
            'permission' => (int) (
                $counts['PERMISSION'] ?? 0
            ),
            'absent' => (int) (
                $counts['ABSENT'] ?? 0
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function teacherAttendance(
        User $user,
        CarbonImmutable $date
    ): array {
        $query = SchoolDataScope
            ::teacherAttendances($user)
            ->whereDate(
                'attendance_date',
                $date->toDateString()
            );

        $total = (clone $query)->count();

        $counts = (clone $query)
            ->selectRaw(
                'status, COUNT(*) AS aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        return [
            'total' => $total,
            'present' => (int) (
                $counts['PRESENT'] ?? 0
            ),
            'late' => (int) (
                $counts['LATE'] ?? 0
            ),
            'sick' => (int) (
                $counts['SICK'] ?? 0
            ),
            'permission' => (int) (
                $counts['PERMISSION'] ?? 0
            ),
            'absent' => (int) (
                $counts['ABSENT'] ?? 0
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function savings(User $user): array
    {
        $accounts =
            SchoolDataScope::savingAccounts(
                $user
            );

        return [
            'accounts' =>
                (clone $accounts)->count(),
            'total_balance' =>
                $this->money(
                    (clone $accounts)
                        ->sum(
                            'current_balance'
                        )
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spp(User $user): array
    {
        $bills =
            SchoolDataScope::sppBills($user);

        $openBills = (clone $bills)
            ->whereNotIn(
                'status',
                [
                    'PAID',
                    'CANCELLED',
                ]
            );

        $amount = (clone $openBills)
            ->sum('amount');

        $paidAmount = (clone $openBills)
            ->sum('paid_amount');

        return [
            'open_bills' =>
                (clone $openBills)->count(),
            'overdue_bills' =>
                (clone $bills)
                    ->where(
                        'status',
                        'OVERDUE'
                    )
                    ->count(),
            'outstanding_amount' =>
                $this->money(
                    (float) $amount
                    - (float) $paidAmount
                ),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function approvals(
        User $user
    ): array {
        $query = Approval::query()
            ->where(
                'status',
                'PENDING'
            );

        if (
            ! $user->hasPermission(
                'approval.view.all'
            )
        ) {
            $query->where(
                'requested_by',
                $user->id
            );
        }

        return [
            'pending' => $query->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function notifications(): array
    {
        return [
            'failed' =>
                NotificationLog::query()
                    ->where(
                        'status',
                        'FAILED'
                    )
                    ->count(),
            'queued' =>
                NotificationLog::query()
                    ->where(
                        'status',
                        'QUEUED'
                    )
                    ->count(),
        ];
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(
                fn (AuditLog $log): array => [
                    'module' => $log->module,
                    'action' => $log->action,
                    'actor' => $log->user
                        ? [
                            'uuid' =>
                                $log->user->uuid,
                            'name' =>
                                $log->user->name,
                        ]
                        : null,
                    'entity_type' =>
                        $log->entity_type,
                    'created_at' =>
                        $log->created_at
                            ?->toIso8601String(),
                ]
            )
            ->values()
            ->all();
    }

    private function money(
        mixed $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }
}
