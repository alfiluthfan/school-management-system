<?php

namespace App\Http\Controllers\Web;

use App\Actions\Attendance\CheckInStudentAction;
use App\Actions\Attendance\CheckInTeacherAction;
use App\Actions\Attendance\CheckOutStudentAction;
use App\Actions\Attendance\CheckOutTeacherAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StudentCheckInRequest;
use App\Http\Requests\Attendance\StudentCheckOutRequest;
use App\Http\Requests\Attendance\TeacherCheckInRequest;
use App\Http\Requests\Attendance\TeacherCheckOutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PortalAttendanceActionController extends Controller
{
    // Reuse the SAME domain Actions/FormRequests as /api/v1. No browser API token,
    // HTTP-to-HTTP proxy, manual identity field, or duplicated attendance logic.
    public function studentCheckIn(
        StudentCheckInRequest $request,
        CheckInStudentAction $action
    ): RedirectResponse {
        Gate::authorize('student-attendance.check-in');
        $student = $request->user()->student;
        abort_unless($student, 403);

        $action->execute(
            actor: $request->user(), student: $student,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $this->accuracy($request->validated('accuracy'))
        );

        return to_route('portal.attendance.index', ['type' => 'students'])
            ->with('success', 'Check-in siswa berhasil dicatat.');
    }

    public function studentCheckOut(
        StudentCheckOutRequest $request,
        CheckOutStudentAction $action
    ): RedirectResponse {
        Gate::authorize('student-attendance.check-out');
        $student = $request->user()->student;
        abort_unless($student, 403);

        $action->execute(
            actor: $request->user(), student: $student,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $this->accuracy($request->validated('accuracy'))
        );

        return to_route('portal.attendance.index', ['type' => 'students'])
            ->with('success', 'Check-out siswa berhasil dicatat.');
    }

    public function teacherCheckIn(
        TeacherCheckInRequest $request,
        CheckInTeacherAction $action
    ): RedirectResponse {
        Gate::authorize('teacher-attendance.check-in');
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403);

        $action->execute(
            actor: $request->user(), teacher: $teacher,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $this->accuracy($request->validated('accuracy'))
        );

        return to_route('portal.attendance.index', ['type' => 'teachers'])
            ->with('success', 'Check-in guru berhasil dicatat.');
    }

    public function teacherCheckOut(
        TeacherCheckOutRequest $request,
        CheckOutTeacherAction $action
    ): RedirectResponse {
        Gate::authorize('teacher-attendance.check-out');
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403);

        $action->execute(
            actor: $request->user(), teacher: $teacher,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: $this->accuracy($request->validated('accuracy'))
        );

        return to_route('portal.attendance.index', ['type' => 'teachers'])
            ->with('success', 'Check-out guru berhasil dicatat.');
    }

    private function accuracy(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
