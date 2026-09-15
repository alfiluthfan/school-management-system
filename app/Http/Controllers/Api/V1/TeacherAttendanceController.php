<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\CheckInTeacherAction;
use App\Actions\Attendance\CheckOutTeacherAction;
use App\Authorization\SchoolDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\TeacherAttendanceIndexRequest;
use App\Http\Requests\Attendance\TeacherCheckInRequest;
use App\Http\Requests\Attendance\TeacherCheckOutRequest;
use App\Http\Resources\Attendance\TeacherAttendanceResource;
use App\Models\Attendance\TeacherAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TeacherAttendanceController extends Controller
{
    public function index(
        TeacherAttendanceIndexRequest $request
    ): AnonymousResourceCollection {
        Gate::authorize(
            'viewAny',
            TeacherAttendance::class
        );

        $query =
            SchoolDataScope::teacherAttendances(
                $request->user()
            )
            ->with([
                'teacher.user',
                'schedule',
                'schoolLocation',
                'correctedBy',
            ]);

        $this->applyFilters(
            $query,
            $request
        );

        $attendances = $query
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->paginate(
                $request->perPage()
            )
            ->withQueryString();

        return TeacherAttendanceResource::collection(
            $attendances
        );
    }

    public function show(
        TeacherAttendance $teacherAttendance
    ): TeacherAttendanceResource {
        Gate::authorize(
            'view',
            $teacherAttendance
        );

        $teacherAttendance->load([
            'teacher.user',
            'schedule',
            'schoolLocation',
            'correctedBy',
        ]);

        return TeacherAttendanceResource::make(
            $teacherAttendance
        );
    }

    public function checkIn(
        TeacherCheckInRequest $request,
        CheckInTeacherAction $action
    ) {
        Gate::authorize(
            'teacher-attendance.check-in'
        );

        $teacher =
            $request->user()->teacher;

        abort_unless($teacher, 403);

        $attendance = $action->execute(
            actor: $request->user(),
            teacher: $teacher,
            latitude:
                (float) $request
                    ->validated('latitude'),
            longitude:
                (float) $request
                    ->validated('longitude'),
            accuracy:
                $request->validated('accuracy')
                    !== null
                        ? (float) $request
                            ->validated(
                                'accuracy'
                            )
                        : null
        );

        $attendance->load([
            'teacher.user',
            'schedule',
            'schoolLocation',
            'correctedBy',
        ]);

        return TeacherAttendanceResource::make(
            $attendance
        )
            ->response()
            ->setStatusCode(201);
    }

    public function checkOut(
        TeacherCheckOutRequest $request,
        CheckOutTeacherAction $action
    ): TeacherAttendanceResource {
        Gate::authorize(
            'teacher-attendance.check-out'
        );

        $teacher =
            $request->user()->teacher;

        abort_unless($teacher, 403);

        $attendance = $action->execute(
            actor: $request->user(),
            teacher: $teacher,
            latitude:
                (float) $request
                    ->validated('latitude'),
            longitude:
                (float) $request
                    ->validated('longitude'),
            accuracy:
                $request->validated('accuracy')
                    !== null
                        ? (float) $request
                            ->validated(
                                'accuracy'
                            )
                        : null
        );

        $attendance->load([
            'teacher.user',
            'schedule',
            'schoolLocation',
            'correctedBy',
        ]);

        return TeacherAttendanceResource::make(
            $attendance
        );
    }

    private function applyFilters(
        Builder $query,
        TeacherAttendanceIndexRequest $request
    ): void {
        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->validated('status')
            );
        }

        if ($request->filled('from')) {
            $query->whereDate(
                'attendance_date',
                '>=',
                $request->validated('from')
            );
        }

        if ($request->filled('to')) {
            $query->whereDate(
                'attendance_date',
                '<=',
                $request->validated('to')
            );
        }

        if ($request->filled('teacher_uuid')) {
            $uuid =
                $request->validated(
                    'teacher_uuid'
                );

            $query->whereHas(
                'teacher',
                fn (Builder $teacherQuery) =>
                    $teacherQuery->where(
                        'uuid',
                        $uuid
                    )
            );
        }

        if ($request->filled('search')) {
            $search = '%'
                .$request->validated('search')
                .'%';

            $query->where(
                function (
                    Builder $scope
                ) use ($search): void {
                    $scope
                        ->whereHas(
                            'teacher.user',
                            fn (
                                Builder $userQuery
                            ) => $userQuery
                                ->where(
                                    'name',
                                    'like',
                                    $search
                                )
                        )
                        ->orWhereHas(
                            'teacher',
                            fn (
                                Builder $teacherQuery
                            ) => $teacherQuery
                                ->where(
                                    'nip',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'employee_number',
                                    'like',
                                    $search
                                )
                        );
                }
            );
        }
    }
}
