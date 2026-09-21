<?php

namespace App\Queries\Reports;

use App\Authorization\SchoolDataScope;
use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use App\Http\Resources\Reporting\ReportExportResource;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use App\Models\Reporting\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read model shared by the reporting page; no calculations are repeated in Vue. */
final class PortalReportPageQuery
{
    /** @return list<ReportType> */
    public function allowedTypes(User $user): array
    {
        return array_values(array_filter(
            ReportType::cases(),
            static fn (ReportType $type): bool => Gate::forUser($user)->allows($type->permission())
        ));
    }

    /** @return list<ReportFormat> */
    public function allowedFormats(User $user): array
    {
        return array_values(array_filter(
            ReportFormat::cases(),
            static fn (ReportFormat $format): bool => Gate::forUser($user)->allows($format->permission())
        ));
    }

    /**
     * An attacker may not pass a UUID outside their data scope, even when the
     * underlying SQL report would otherwise just return an empty result.
     */
    public function authorizeFilter(User $user, ReportType $type, ?string $classUuid, ?string $teacherUuid): void
    {
        if ($classUuid !== null) {
            abort_unless($type === ReportType::StudentAttendance, 422);
            abort_unless(SchoolDataScope::schoolClasses($user)->where('uuid', $classUuid)->exists(), 404);
        }
        if ($teacherUuid !== null) {
            abort_unless($type === ReportType::TeacherAttendance, 422);
            $allowed = $user->hasPermission('teacher-attendance.view.all')
                ? Teacher::query()->where('uuid', $teacherUuid)->exists()
                : SchoolDataScope::teacherAttendances($user)
                    ->whereHas('teacher', fn ($query) => $query->where('uuid', $teacherUuid))->exists();
            abort_unless($allowed, 404);
        }
    }

    /** @return array<string,mixed> */
    public function options(User $user, ReportType $type): array
    {
        $classes = [];
        $teachers = [];
        if ($type === ReportType::StudentAttendance) {
            $classes = SchoolDataScope::schoolClasses($user)
                ->orderBy('name')->limit(100)->get(['uuid', 'name', 'code'])
                ->map(fn ($class): array => [
                    'uuid' => $class->uuid,
                    'label' => $class->name ?: $class->code,
                ])->all();
        }
        if ($type === ReportType::TeacherAttendance) {
            $query = $user->hasPermission('teacher-attendance.view.all')
                ? Teacher::query() : Teacher::query()->where('user_id', $user->id);
            $teachers = $query->with('user')->orderBy('id')->limit(100)->get()
                ->map(fn ($teacher): array => [
                    'uuid' => $teacher->uuid,
                    'label' => $teacher->user?->name ?? $teacher->uuid,
                ])->all();
        }
        return ['classes' => $classes, 'teachers' => $teachers];
    }

    /** @return array<string,mixed> */
    public function report(User $user, ReportType $type, CarbonImmutable $from, CarbonImmutable $to,
        ?string $classUuid, ?string $teacherUuid,
        StudentAttendanceReportQuery $student, TeacherAttendanceReportQuery $teacher,
        SavingsReportQuery $savings, SppReportQuery $spp): array
    {
        return match ($type) {
            ReportType::StudentAttendance => $student->execute($user, $from, $to, $classUuid),
            ReportType::TeacherAttendance => $teacher->execute($user, $from, $to, $teacherUuid),
            ReportType::Savings => $savings->execute($user, $from, $to),
            ReportType::Spp => $spp->execute($user, $from, $to),
        };
    }

    /** @return array<string,mixed> */
    public function exports(Request $request, ?string $status = null): array
    {
        $user = $request->user();
        $types = array_map(static fn (ReportType $t): string => $t->value, $this->allowedTypes($user));
        $formats = array_map(static fn (ReportFormat $f): string => $f->value, $this->allowedFormats($user));
        if ($types === [] || $formats === []) {
            return ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1,
                'previous' => null, 'next' => null];
        }
        $query = ReportExport::query()->where('requested_by', $user->id)
            ->whereIn('report_type', $types)->whereIn('format', $formats);
        if ($status !== null) {
            $query->where('status', ReportExportStatus::from($status)->value);
        }
        $pages = $query->latest('id')->paginate(10, ['*'], 'export_page')->withQueryString();
        return [
            'data' => $pages->getCollection()->map(function (ReportExport $row) use ($request): array {
                $data = (new ReportExportResource($row))->toArray($request);
                if ($data['download_url'] !== null) {
                    // A web-session route, not a direct API URL. Rechecks all security conditions.
                    $data['download_url'] = route('portal.reports.exports.download', $row);
                }
                return $data;
            })->values()->all(),
            'total' => $pages->total(),
            'current_page' => $pages->currentPage(),
            'last_page' => $pages->lastPage(),
            'previous' => $pages->previousPageUrl(),
            'next' => $pages->nextPageUrl(),
        ];
    }
}
