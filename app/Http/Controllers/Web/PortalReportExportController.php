<?php

namespace App\Http\Controllers\Web;

use App\Authorization\SchoolDataScope;
use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CreateReportExportRequest;
use App\Http\Resources\Reporting\ReportExportResource;
use App\Jobs\Reporting\GenerateReportExport;
use App\Models\Academic\Teacher;
use App\Models\Reporting\ReportExport;
use App\Services\Reporting\ReportExportAccess;
use App\Services\System\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Session-backed bridge reusing existing validation, job and export access. */
final class PortalReportExportController extends Controller
{
    public function store(CreateReportExportRequest $request, ReportExportAccess $access,
        AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $type = ReportType::from($data['report_type']);
        $format = ReportFormat::from($data['format']);
        abort_unless($access->allows($request->user(), $type, $format), 403);

        $filters = array_filter([
            'class_uuid' => $data['class_uuid'] ?? null,
            'teacher_uuid' => $data['teacher_uuid'] ?? null,
        ], static fn ($value): bool => $value !== null);

        if (isset($filters['class_uuid'])) {
            abort_unless(SchoolDataScope::schoolClasses($request->user())
                ->where('uuid', $filters['class_uuid'])->exists(), 404);
        }
        if (isset($filters['teacher_uuid'])) {
            $scoped = SchoolDataScope::teacherAttendances($request->user())
                ->whereHas('teacher', fn ($query) => $query->where('uuid', $filters['teacher_uuid']))
                ->exists();
            if ($request->user()->hasPermission('teacher-attendance.view.all')) {
                $scoped = Teacher::query()->where('uuid', $filters['teacher_uuid'])->exists();
            }
            abort_unless($scoped, 404);
        }
        $export = DB::transaction(function () use ($request, $data, $type, $format, $filters, $audit): ReportExport {
            $record = ReportExport::query()->create([
                'requested_by' => $request->user()->id,
                'report_type' => $type,
                'format' => $format,
                'from_date' => $data['from'],
                'to_date' => $data['to'],
                'filters' => $filters,
                'status' => ReportExportStatus::Queued,
            ]);
            $audit->log(actor: $request->user(), module: 'report', action: 'EXPORT_REQUESTED',
                entity: $record, newValues: [
                    'type' => $type->value, 'format' => $format->value,
                    'from' => $data['from'], 'to' => $data['to'], 'filters' => $filters,
                ]);
            return $record;
        }, 3);

        GenerateReportExport::dispatch($export->id)
            ->onQueue((string) config('report_exports.queue', 'exports'))->afterCommit();

        $params = ['report_type' => $type->value, 'from' => $data['from'], 'to' => $data['to']];
        if (isset($filters['class_uuid'])) {
            $params['class_uuid'] = $filters['class_uuid'];
        }
        if (isset($filters['teacher_uuid'])) {
            $params['teacher_uuid'] = $filters['teacher_uuid'];
        }
        return to_route('portal.reports.index', $params)
            ->with('success', 'Ekspor masuk antrean. Statusnya akan diperbarui pada daftar ekspor.');
    }

    private function authorized(Request $request, ReportExport $reportExport,
        ReportExportAccess $access): void
    {
        abort_unless($reportExport->requested_by === $request->user()->id, 404);
        abort_unless($access->canDownload($request->user(), $reportExport), 403);
    }

    public function status(Request $request, ReportExport $reportExport,
        ReportExportAccess $access): JsonResponse
    {
        $this->authorized($request, $reportExport, $access);
        $data = (new ReportExportResource($reportExport))->toArray($request);
        if ($data['download_url'] !== null) {
            $data['download_url'] = route('portal.reports.exports.download', $reportExport);
        }
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, ReportExport $reportExport,
        ReportExportAccess $access, AuditLogger $audit): StreamedResponse
    {
        $this->authorized($request, $reportExport, $access);
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
        $audit->log(actor: $request->user(), module: 'report', action: 'EXPORT_DOWNLOADED',
            entity: $reportExport, metadata: ['report_uuid' => $reportExport->uuid]);
        return $disk->download($reportExport->file_path, $reportExport->file_name, [
            'Content-Type' => $reportExport->format->mimeType(),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
