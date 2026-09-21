<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ReportRangeRequest;
use App\Http\Requests\Reporting\StudentAttendanceReportRequest;
use App\Http\Requests\Reporting\TeacherAttendanceReportRequest;
use App\Queries\Reports\SavingsReportQuery;
use App\Queries\Reports\SppReportQuery;
use App\Queries\Reports\StudentAttendanceReportQuery;
use App\Queries\Reports\TeacherAttendanceReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function studentAttendance(
        StudentAttendanceReportRequest $request,
        StudentAttendanceReportQuery $query
    ): JsonResponse {
        Gate::authorize(
            'report.attendance.student'
        );

        return response()->json([
            'data' => $query->execute(
                user: $request->user(),
                from: $request->fromDate(),
                to: $request->toDate(),
                classUuid:
                    $request->validated(
                        'class_uuid'
                    )
            ),
        ]);
    }

    public function teacherAttendance(
        TeacherAttendanceReportRequest $request,
        TeacherAttendanceReportQuery $query
    ): JsonResponse {
        Gate::authorize(
            'report.attendance.teacher'
        );

        return response()->json([
            'data' => $query->execute(
                user: $request->user(),
                from: $request->fromDate(),
                to: $request->toDate(),
                teacherUuid:
                    $request->validated(
                        'teacher_uuid'
                    )
            ),
        ]);
    }

    public function savings(
        ReportRangeRequest $request,
        SavingsReportQuery $query
    ): JsonResponse {
        Gate::authorize(
            'report.saving'
        );

        return response()->json([
            'data' => $query->execute(
                user: $request->user(),
                from: $request->fromDate(),
                to: $request->toDate()
            ),
        ]);
    }

    public function spp(
        ReportRangeRequest $request,
        SppReportQuery $query
    ): JsonResponse {
        Gate::authorize(
            'report.spp'
        );

        return response()->json([
            'data' => $query->execute(
                user: $request->user(),
                from: $request->fromDate(),
                to: $request->toDate()
            ),
        ]);
    }
}
