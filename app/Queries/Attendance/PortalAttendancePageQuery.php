<?php

namespace App\Queries\Attendance;

use App\Authorization\SchoolDataScope;
use App\Enums\Attendance\AttendanceStatus;
use App\Models\Academic\Teacher;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Models\Auth\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class PortalAttendancePageQuery
{
    /**
     * Caller MUST authorize viewAny for the requested type first.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function execute(User $user, string $type, array $filters): array
    {
        $studentMode = $type === 'students';
        $query = $studentMode
            ? SchoolDataScope::studentAttendances($user)
                ->with(['student.user', 'schoolClass', 'schoolLocation'])
            : SchoolDataScope::teacherAttendances($user)
                ->with(['teacher.user', 'schoolLocation']);

        if (! empty($filters['from'])) {
            $query->whereDate('attendance_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('attendance_date', '<=', $filters['to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($studentMode && ! empty($filters['class_uuid'])) {
            $query->whereHas('schoolClass', fn (Builder $q) =>
                $q->where('uuid', $filters['class_uuid'])
            );
        }
        if (! $studentMode && ! empty($filters['teacher_uuid'])) {
            $query->whereHas('teacher', fn (Builder $q) =>
                $q->where('uuid', $filters['teacher_uuid'])
            );
        }
        if (! empty($filters['search'])) {
            // Group OR predicates INSIDE the already authorized data scope.
            $search = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($studentMode, $search): void {
                if ($studentMode) {
                    $q->whereHas('student.user', fn (Builder $u) =>
                        $u->where('name', 'like', $search)
                    )->orWhereHas('student', fn (Builder $s) =>
                        $s->where('nis', 'like', $search)
                    );
                } else {
                    $q->whereHas('teacher.user', fn (Builder $u) =>
                        $u->where('name', 'like', $search)
                    )->orWhereHas('teacher', fn (Builder $t) =>
                        $t->where('nip', 'like', $search)
                          ->orWhere('employee_number', 'like', $search)
                    );
                }
            });
        }

        // Aggregate only the scoped, filtered rows; this is NOT an attendance rate.
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $summary = [];
        foreach (AttendanceStatus::cases() as $status) {
            $summary[strtolower($status->value)] = (int) ($counts[$status->value] ?? 0);
        }

        $paginator = $query->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $self = $studentMode ? $user->student : $user->teacher;
        $permissionPrefix = $studentMode ? 'student-attendance' : 'teacher-attendance';
        $canCheckIn = $self !== null && $user->hasPermission($permissionPrefix.'.check-in');
        $canCheckOut = $self !== null && $user->hasPermission($permissionPrefix.'.check-out');

        $ownToday = null;
        if ($canCheckIn || $canCheckOut) {
            // Keep this separate from user-supplied filters and ALL monitoring rows.
            $ownerKey = $studentMode ? 'student_id' : 'teacher_id';
            $own = ($studentMode
                ? SchoolDataScope::studentAttendances($user)
                : SchoolDataScope::teacherAttendances($user))
                    ->where($ownerKey, $self->id)
                    ->whereDate('attendance_date', $today)
                    ->first();
            if ($own) {
                $ownToday = [
                    'status' => $own->status->value,
                    'status_label' => $own->status->label(),
                    'check_in_at' => $own->check_in_at?->toIso8601String(),
                    'check_out_at' => $own->check_out_at?->toIso8601String(),
                ];
            }
        }

        $classes = [];
        if ($studentMode && $user->hasAnyPermission([
            'class.view.all', 'class.view.assigned', 'class.view.own', 'class.view.child',
        ])) {
            $classes = SchoolDataScope::schoolClasses($user)
                ->orderBy('name')->limit(200)->get(['uuid', 'name', 'code'])
                ->map(fn ($class) => [
                    'uuid' => $class->uuid,
                    'name' => $class->name,
                    'code' => $class->code,
                ])->all();
        }

        $teachers = [];
        if (! $studentMode && $user->hasPermission('teacher-attendance.view.all')
            && $user->hasPermission('teacher.view.all')) {
            $teachers = Teacher::query()->with('user')->orderBy('id')->limit(200)
                ->get()->map(fn (Teacher $teacher) => [
                    'uuid' => $teacher->uuid,
                    'name' => $teacher->user?->name,
                    'nip' => $teacher->nip,
                ])->all();
        }

        return [
            'type' => $type,
            'today_date' => $today,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'search' => $filters['search'] ?? '',
                'class_uuid' => $studentMode ? ($filters['class_uuid'] ?? '') : '',
                'teacher_uuid' => $studentMode ? '' : ($filters['teacher_uuid'] ?? ''),
                'per_page' => (int) ($filters['per_page'] ?? 20),
            ],
            'summary' => $summary,
            'records' => [
                'data' => $paginator->getCollection()->map(fn ($item) => $this->row($item, $studentMode))->all(),
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'can' => [
                'check_in' => $canCheckIn,
                'check_out' => $canCheckOut,
            ],
            'own_today' => $ownToday,
            'classes' => $classes,
            'teachers' => $teachers,
        ];
    }

    private function row(StudentAttendance|TeacherAttendance $attendance, bool $studentMode): array
    {
        $person = $studentMode ? $attendance->student : $attendance->teacher;
        return [
            'uuid' => $attendance->uuid,
            'date' => $attendance->attendance_date?->toDateString(),
            'person' => [
                'uuid' => $person?->uuid,
                'name' => $person?->user?->name,
                'identifier' => $studentMode ? $person?->nis : $person?->nip,
            ],
            'class' => $studentMode && $attendance->schoolClass ? [
                'uuid' => $attendance->schoolClass->uuid,
                'name' => $attendance->schoolClass->name,
            ] : null,
            'status' => $attendance->status->value,
            'status_label' => $attendance->status->label(),
            'check_in_at' => $attendance->check_in_at?->toIso8601String(),
            'check_out_at' => $attendance->check_out_at?->toIso8601String(),
            'late_minutes' => (int) $attendance->late_minutes,
            'school_location' => $attendance->schoolLocation?->name,
        ];
    }
}
