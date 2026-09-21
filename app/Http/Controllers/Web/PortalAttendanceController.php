<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\PortalAttendanceIndexRequest;
use App\Models\Attendance\StudentAttendance;
use App\Models\Attendance\TeacherAttendance;
use App\Queries\Attendance\PortalAttendancePageQuery;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PortalAttendanceController extends Controller
{
    public function __invoke(
        PortalAttendanceIndexRequest $request,
        PortalAttendancePageQuery $query
    ): Response {
        $studentAccess = Gate::allows('viewAny', StudentAttendance::class);
        $teacherAccess = Gate::allows('viewAny', TeacherAttendance::class);
        abort_unless($studentAccess || $teacherAccess, 403);

        $validated = $request->validated();
        $type = $validated['type'] ?? ($studentAccess ? 'students' : 'teachers');
        abort_unless($type === 'students' ? $studentAccess : $teacherAccess, 403);
        Gate::authorize('viewAny', $type === 'students'
            ? StudentAttendance::class : TeacherAttendance::class);

        $tabs = [];
        if ($studentAccess) {
            $tabs[] = ['type' => 'students', 'label' => 'Absensi siswa'];
        }
        if ($teacherAccess) {
            $tabs[] = ['type' => 'teachers', 'label' => 'Absensi guru'];
        }

        return Inertia::render('Attendance/Index', [
            'tabs' => $tabs,
            'attendance' => fn (): array => $query->execute(
                $request->user(), $type, $validated
            ),
        ]);
    }
}
