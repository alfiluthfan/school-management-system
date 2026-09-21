<?php

namespace App\Http\Controllers\Web;

use App\Enums\Reporting\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\PortalReportIndexRequest;
use App\Queries\Reports\PortalReportPageQuery;
use App\Queries\Reports\SavingsReportQuery;
use App\Queries\Reports\SppReportQuery;
use App\Queries\Reports\StudentAttendanceReportQuery;
use App\Queries\Reports\TeacherAttendanceReportQuery;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PortalReportingController extends Controller
{
    public function index(PortalReportIndexRequest $request, PortalReportPageQuery $page,
        StudentAttendanceReportQuery $student, TeacherAttendanceReportQuery $teacher,
        SavingsReportQuery $savings, SppReportQuery $spp): Response
    {
        $user = $request->user();
        $types = $page->allowedTypes($user);
        abort_if($types === [], 403);
        $input = $request->validated();
        $type = isset($input['report_type']) ? ReportType::from($input['report_type']) : $types[0];
        Gate::authorize($type->permission());
        $classUuid = $input['class_uuid'] ?? null;
        $teacherUuid = $input['teacher_uuid'] ?? null;
        if ($classUuid !== null && $type !== ReportType::StudentAttendance) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'class_uuid' => 'Filter kelas hanya berlaku untuk absensi siswa.',
            ]);
        }
        if ($teacherUuid !== null && $type !== ReportType::TeacherAttendance) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'teacher_uuid' => 'Filter guru hanya berlaku untuk absensi guru.',
            ]);
        }
        $page->authorizeFilter($user, $type, $classUuid, $teacherUuid);
        $formats = $page->allowedFormats($user);
        return Inertia::render('Reports/Index', [
            'reporting' => [
                'filters' => ['report_type' => $type->value, 'from' => $input['from'],
                    'to' => $input['to'], 'class_uuid' => $classUuid ?? '',
                    'teacher_uuid' => $teacherUuid ?? '', 'export_status' => $input['export_status'] ?? ''],
                'types' => array_map(static fn (ReportType $item): array => [
                    'value' => $item->value, 'label' => $item->title(),
                ], $types),
                'formats' => array_map(static fn ($item): array => [
                    'value' => $item->value,
                    'label' => $item->value === 'pdf' ? 'PDF' : 'Excel (.xlsx)',
                ], $formats),
                'options' => $page->options($user, $type),
                'can_export' => $formats !== [],
            ],
            'report' => fn (): array => $page->report($user, $type,
                $request->fromDate(), $request->toDate(), $classUuid, $teacherUuid,
                $student, $teacher, $savings, $spp),
            'exports' => fn (): array => $page->exports($request, $input['export_status'] ?? null),
        ]);
    }
}
