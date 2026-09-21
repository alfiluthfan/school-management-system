<?php

namespace App\Queries\Reports;

use App\Authorization\SchoolDataScope;
use App\Models\Academic\SchoolClass;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StudentAttendanceReportQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $classUuid = null
    ): array {
        $query =
            SchoolDataScope::studentAttendances(
                $user
            )
            ->whereBetween(
                'attendance_date',
                [
                    $from->toDateString(),
                    $to->toDateString(),
                ]
            );

        if ($classUuid) {
            $query->whereHas(
                'schoolClass',
                fn (Builder $classQuery) =>
                    $classQuery->where(
                        'uuid',
                        $classUuid
                    )
            );
        }

        $summary = $this->summary($query);
        $daily = $this->daily($query);
        $classes = $this->byClass($query);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'filters' => [
                'class_uuid' => $classUuid,
            ],
            'summary' => $summary,
            'daily' => $daily,
            'classes' => $classes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(
        Builder $query
    ): array {
        $counts = (clone $query)
            ->selectRaw(
                'status, COUNT(*) AS aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        $total = array_sum(
            $counts
                ->map(
                    fn (mixed $value): int =>
                        (int) $value
                )
                ->all()
        );

        return [
            'total_records' => $total,
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
                        ->sum('late_minutes')
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
                'maximum' => (int) (
                    (clone $query)
                        ->max('late_minutes')
                    ?? 0
                ),
            ],
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function daily(
        Builder $query
    ): array {
        return (clone $query)
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
                "SUM(CASE WHEN status = 'SICK' THEN 1 ELSE 0 END) AS sick"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'PERMISSION' THEN 1 ELSE 0 END) AS permission_count"
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
                    'sick' =>
                        (int) $row->sick,
                    'permission' =>
                        (int) $row
                            ->permission_count,
                    'absent' =>
                        (int) $row->absent,
                ]
            )
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byClass(
        Builder $query
    ): array {
        $rows = (clone $query)
            ->selectRaw(
                'class_id, COUNT(*) AS total'
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
            ->groupBy('class_id')
            ->orderByDesc('total')
            ->get();

        $classes = SchoolClass::query()
            ->whereIn(
                'id',
                $rows->pluck('class_id')
                    ->filter()
            )
            ->get()
            ->keyBy('id');

        return $rows
            ->map(
                function (
                    $row
                ) use ($classes): array {
                    $class = $classes->get(
                        $row->class_id
                    );

                    return [
                        'class' => $class
                            ? [
                                'uuid' =>
                                    $class->uuid,
                                'code' =>
                                    $class->code,
                                'name' =>
                                    $class->name,
                                'grade_level' =>
                                    $class
                                        ->grade_level,
                            ]
                            : null,
                        'total' =>
                            (int) $row->total,
                        'present' =>
                            (int) $row->present,
                        'late' =>
                            (int) $row->late,
                        'absent' =>
                            (int) $row->absent,
                    ];
                }
            )
            ->values()
            ->all();
    }
}
