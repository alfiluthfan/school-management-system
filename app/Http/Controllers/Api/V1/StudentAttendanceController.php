<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\CheckInStudentAction;
use App\Actions\Attendance\CheckOutStudentAction;
use App\Authorization\SchoolDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StudentCheckInRequest;
use App\Http\Requests\Attendance\StudentCheckOutRequest;
use App\Http\Requests\Common\IndexRequest;
use App\Http\Resources\Attendance\StudentAttendanceResource;
use App\Models\Attendance\StudentAttendance;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StudentAttendanceController extends Controller
{
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StudentAttendance::class);

        $attendances = SchoolDataScope::studentAttendances($request->user())
            ->with(['student.user', 'schoolClass', 'schedule', 'schoolLocation'])
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->paginate($request->perPage())
            ->withQueryString();

        return StudentAttendanceResource::collection($attendances);
    }

    public function show(
        StudentAttendance $studentAttendance
    ): StudentAttendanceResource {
        Gate::authorize('view', $studentAttendance);

        $studentAttendance->load([
            'student.user',
            'schoolClass',
            'schedule',
            'schoolLocation',
        ]);

        return StudentAttendanceResource::make($studentAttendance);
    }

    public function checkIn(
        StudentCheckInRequest $request,
        CheckInStudentAction $action
    ) {
        Gate::authorize('student-attendance.check-in');

        $student = $request->user()->student;
        abort_unless($student, 403);

        $attendance = $action->execute(
            actor: $request->user(),
            student: $student,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $request->validated('accuracy') !== null
                ? (float) $request->validated('accuracy')
                : null,
        );

        $attendance->load([
            'student.user',
            'schoolClass',
            'schedule',
            'schoolLocation',
        ]);

        return StudentAttendanceResource::make($attendance)
            ->response()
            ->setStatusCode(201);
    }

    public function checkOut(
        StudentCheckOutRequest $request,
        CheckOutStudentAction $action
    ): StudentAttendanceResource {
        Gate::authorize('student-attendance.check-out');

        $student = $request->user()->student;
        abort_unless($student, 403);

        $attendance = $action->execute(
            actor: $request->user(),
            student: $student,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $request->validated('accuracy') !== null
                ? (float) $request->validated('accuracy')
                : null,
        );

        $attendance->load([
            'student.user',
            'schoolClass',
            'schedule',
            'schoolLocation',
        ]);

        return StudentAttendanceResource::make($attendance);
    }
}
