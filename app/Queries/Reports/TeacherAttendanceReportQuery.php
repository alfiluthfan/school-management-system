<?php

namespace App\Queries\Reports;

use App\Authorization\SchoolDataScope;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class TeacherAttendanceReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $teacherUuid = null
    ): array {
        $query =
            SchoolDataScope::teacherAttendances(
                $user
            )
            ->whereBetween(
                'attendance_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            );

        if ($teacherUuid) {
            $query->whereHas(
                'teacher',
                fn (Builder $teacherQuery) =>
                    $teacherQuery->where(
                        'uuid',
                        $teacherUuid
                    )
            );
        }

        $counts = (clone $query)
            ->selectRaw(
                'status, COUNT(*) AS aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        $daily = (clone $query)
            ->selectRaw(
                'DATE(attendance_date) AS attendance_day'
            )
            ->selectRaw(
                'COUNT(*) AS total'
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) AS present"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) AS late"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) AS absent"
            )
            ->groupBy('attendance_day')
            ->orderBy('attendance_day')
            ->get()
            ->map(
                fn ($row): array => [
                    'date' =>
                        (string) $row
                            ->attendance_day,
                    'total' =>
                        (int) $row->total,
                    'present' =>
                        (int) $row->present,
                    'late' =>
                        (int) $row->late,
                    'absent' =>
                        (int) $row->absent,
                ]
            )
            ->values()
            ->all();

        $teacherRows = (clone $query)
            ->selectRaw(
                'teacher_id, COUNT(*) AS total'
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) AS present"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'LATE' THEN 1 ELSE 0 END) AS late"
            )
            ->selectRaw(
                'SUM(late_minutes) AS late_minutes'
            )
            ->groupBy('teacher_id')
            ->orderByDesc('late')
            ->get();

        $teachers = Teacher::query()
            ->with('user')
            ->whereIn(
                'id',
                $teacherRows
                    ->pluck('teacher_id')
                    ->filter()
            )
            ->get()
            ->keyBy('id');

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'teacher_uuid' =>
                    $teacherUuid,
            ],
            'summary' => [
                'total_records' =>
                    (int) (
                        (clone $query)
                            ->count()
                    ),
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
                'late_minutes' => [
                    'total' => (int) (
                        (clone $query)
                            ->sum(
                                'late_minutes'
                            )
                    ),
                    'average' => round(
                        (float) (
                            (clone $query)
                                ->where(
                                    'late_minutes',
                                    '>',
                                    0
                                )
                                ->avg(
                                    'late_minutes'
                                )
                            ?? 0
                        ),
                        2
                    ),
                ],
            ],
            'daily' => $daily,
            'teachers' =>
                $teacherRows
                    ->map(
                        function (
                            $row
                        ) use ($teachers): array {
                            $teacher =
                                $teachers->get(
                                    $row->teacher_id
                                );

                            return [
                                'teacher' =>
                                    $teacher
                                        ? [
                                            'uuid' =>
                                                $teacher
                                                    ->uuid,
                                            'nip' =>
                                                $teacher
                                                    ->nip,
                                            'name' =>
                                                $teacher
                                                    ->user
                                                    ?->name,
                                        ]
                                        : null,
                                'total' =>
                                    (int) $row
                                        ->total,
                                'present' =>
                                    (int) $row
                                        ->present,
                                'late' =>
                                    (int) $row
                                        ->late,
                                'late_minutes' =>
                                    (int) $row
                                        ->late_minutes,
                            ];
                        }
                    )
                    ->values()
                    ->all(),
        ];
    }
}
