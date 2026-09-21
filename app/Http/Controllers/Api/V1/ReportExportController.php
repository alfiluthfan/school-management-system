<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\SchoolDataScope;
use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CreateReportExportRequest;
use App\Http\Resources\Reporting\ReportExportResource;
use App\Jobs\Reporting\GenerateReportExport;
use App\Models\Reporting\ReportExport;
use App\Services\Reporting\ReportExportAccess;
use App\Services\System\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportController extends Controller
{
    public function store(
        CreateReportExportRequest $request,
        ReportExportAccess $access,
        AuditLogger $audit
    ) {
        $type = ReportType::from($request->validated('report_type'));
        $format = ReportFormat::from($request->validated('format'));
        abort_unless($access->allows($request->user(), $type, $format), 403);

        $filters = array_filter([
            'class_uuid' => $request->validated('class_uuid'),
            'teacher_uuid' => $request->validated('teacher_uuid'),
        ], static fn ($value) => $value !== null);

        // A supplied filter must already be within the requester's data scope.
        // This avoids leaking information through guessed UUIDs.
        if (isset($filters['class_uuid'])) {
            abort_unless(SchoolDataScope::schoolClasses($request->user())
                ->where('uuid', $filters['class_uuid'])->exists(), 404);
        }
        if (isset($filters['teacher_uuid'])) {
            $scoped = SchoolDataScope::teacherAttendances($request->user())
                ->whereHas('teacher', fn ($q) => $q->where('uuid', $filters['teacher_uuid']))
                ->exists();
            // For global viewers, a teacher with zero attendance rows may still be filtered.
            if ($request->user()->hasPermission('teacher-attendance.view.all')) {
                $scoped = \App\Models\Academic\Teacher::query()
                    ->where('uuid', $filters['teacher_uuid'])->exists();
            }
            abort_unless($scoped, 404);
        }

        $export = DB::transaction(function () use ($request, $type, $format, $filters, $audit) {
            $record = ReportExport::query()->create([
                'requested_by' => $request->user()->id,
                'report_type' => $type,
                'format' => $format,
                'from_date' => $request->validated('from'),
                'to_date' => $request->validated('to'),
                'filters' => $filters,
                'status' => ReportExportStatus::Queued,
            ]);
            $audit->log(
                actor: $request->user(), module: 'report', action: 'EXPORT_REQUESTED',
                entity: $record, newValues: [
                    'type' => $type->value, 'format' => $format->value,
                    'from' => $request->validated('from'), 'to' => $request->validated('to'),
                    'filters' => $filters,
                ]
            );
            return $record;
        }, 3);

        GenerateReportExport::dispatch($export->id)
            ->onQueue((string) config('report_exports.queue', 'exports'))
            ->afterCommit();

        return ReportExportResource::make($export)
            ->response()->setStatusCode(202);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $allowedTypes = array_values(array_map(
            static fn (ReportType $type) => $type->value,
            array_filter(ReportType::cases(),
                static fn (ReportType $type) => $user->hasPermission($type->permission()))
        ));
        $allowedFormats = array_values(array_map(
            static fn (ReportFormat $format) => $format->value,
            array_filter(ReportFormat::cases(),
                static fn (ReportFormat $format) => $user->hasPermission($format->permission()))
        ));
        abort_unless($user->is_active && $allowedTypes !== [] && $allowedFormats !== [], 403);
        $perPage = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ])['per_page'] ?? 20;

        return ReportExportResource::collection(
            ReportExport::query()->where('requested_by', $user->id)
                ->whereIn('report_type', $allowedTypes)
                ->whereIn('format', $allowedFormats)
                ->latest('id')->paginate((int) $perPage)
        );
    }

    public function show(
        Request $request,
        ReportExport $reportExport,
        ReportExportAccess $access
    ): ReportExportResource {
        abort_unless($reportExport->requested_by === $request->user()->id, 404);
        abort_unless($access->canDownload($request->user(), $reportExport), 403);
        return ReportExportResource::make($reportExport);
    }

    public function download(
        Request $request,
        ReportExport $reportExport,
        ReportExportAccess $access,
        AuditLogger $audit
    ): StreamedResponse {
        abort_unless($reportExport->requested_by === $request->user()->id, 404);
        abort_unless($access->canDownload($request->user(), $reportExport), 403);
        if ($reportExport->status === ReportExportStatus::Ready
            && ($reportExport->expires_at === null || $reportExport->expires_at->isPast())) {
            abort(410, 'File laporan sudah kedaluwarsa.');
        }
        if ($reportExport->status === ReportExportStatus::Expired) {
            abort(410, 'File laporan sudah kedaluwarsa.');
        }
        abort_unless($reportExport->status === ReportExportStatus::Ready, 409,
            'File belum tersedia. Periksa status ekspor.');
        $disk = Storage::disk($reportExport->storage_disk);
        abort_unless($reportExport->file_path && $disk->exists($reportExport->file_path), 410,
            'File laporan tidak tersedia lagi.');

        $audit->log(
            actor: $request->user(), module: 'report', action: 'EXPORT_DOWNLOADED',
            entity: $reportExport,
            metadata: ['report_uuid' => $reportExport->uuid]
        );

        return $disk->download(
            $reportExport->file_path,
            $reportExport->file_name,
            [
                'Content-Type' => $reportExport->format->mimeType(),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
