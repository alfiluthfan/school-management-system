<?php

namespace App\Actions\Attendance;

use App\Models\Attendance\StudentAttendance;
use App\Models\Auth\User;
use App\Services\Attendance\AttendanceCorrectionValidator;
use App\Services\System\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CorrectStudentAttendanceAction
{
    public function __construct(
        private readonly AttendanceCorrectionValidator $validator,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function execute(
        User $actor,
        StudentAttendance $attendance,
        array $changes,
        string $reason
    ): StudentAttendance {
        return DB::transaction(function () use (
            $actor,
            $attendance,
            $changes,
            $reason
        ): StudentAttendance {
            $locked = StudentAttendance::query()
                ->with('schedule')
                ->lockForUpdate()
                ->findOrFail($attendance->id);

            $resolved = $this->validator->resolve(
                $locked,
                $changes
            );

            $oldValues = $this->snapshot(
                $locked
            );

            $locked->forceFill([
                'status' =>
                    $resolved['status'],
                'check_in_at' =>
                    $resolved['check_in_at'],
                'check_out_at' =>
                    $resolved['check_out_at'],
                'late_minutes' =>
                    $resolved['late_minutes'],
                'notes' =>
                    $resolved['notes'],
                'corrected_by' =>
                    $actor->id,
                'correction_reason' =>
                    $reason,
            ])->save();

            $fresh = $locked->fresh();

            $this->auditLogger->log(
                actor: $actor,
                module: 'attendance',
                action: 'CORRECTION',
                entity: $fresh,
                oldValues: $oldValues,
                newValues:
                    $this->snapshot($fresh),
                metadata: [
                    'correction_reason' =>
                        $reason,
                ]
            );

            return $fresh->load([
                'student.user',
                'schoolClass',
                'schedule',
                'correctedBy',
            ]);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(
        StudentAttendance $attendance
    ): array {
        return [
            'status' =>
                $attendance->status->value,
            'check_in_at' =>
                $attendance->check_in_at
                    ?->toIso8601String(),
            'check_out_at' =>
                $attendance->check_out_at
                    ?->toIso8601String(),
            'late_minutes' =>
                $attendance->late_minutes,
            'notes' =>
                $attendance->notes,
            'corrected_by' =>
                $attendance->corrected_by,
            'correction_reason' =>
                $attendance->correction_reason,
        ];
    }
}
