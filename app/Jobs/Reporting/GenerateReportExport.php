<?php

namespace App\Jobs\Reporting;

use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Models\Reporting\ReportExport;
use App\Services\Reporting\ExcelReportRenderer;
use App\Services\Reporting\PdfReportRenderer;
use App\Services\Reporting\ReportExportAccess;
use App\Services\Reporting\ReportExportDocumentFactory;
use App\Services\Reporting\ReportExportScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public array $backoff = [15, 60];

    public function __construct(public readonly int $exportId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('report-export:'.$this->exportId))
            ->releaseAfter(30)->expireAfter(900)];
    }

    public function handle(
        ReportExportDocumentFactory $documents,
        ReportExportAccess $access,
        ReportExportScope $scope,
        PdfReportRenderer $pdf,
        ExcelReportRenderer $excel
    ): void {
        $claimed = DB::transaction(function (): bool {
            $export = ReportExport::query()->lockForUpdate()->findOrFail($this->exportId);
            if (! in_array($export->status, [
                ReportExportStatus::Queued, ReportExportStatus::Processing,
            ], true)) {
                return false;
            }
            $export->forceFill([
                'status' => ReportExportStatus::Processing,
                'started_at' => $export->started_at ?? now(),
            ])->save();
            return true;
        }, 3);

        if (! $claimed) {
            return; // READY/FAILED/EXPIRED are idempotent no-ops.
        }

        $export = ReportExport::query()->with('requester')->findOrFail($this->exportId);
        $user = $export->requester;
        if (! $user || ! $access->allows($user, $export->report_type, $export->format)) {
            throw new RuntimeException('Hak akses laporan telah berubah.');
        }

        // Scope is recomputed with the CURRENT user and relationships, never
        // with query text, data rows, or a user-provided identity from the job.
        $scopeHash = $scope->hash($user, $export->report_type);
        $document = $documents->build($export, $user);
        if (! hash_equals($scopeHash, $scope->hash($user, $export->report_type))) {
            throw new RuntimeException('Lingkup akses berubah saat ekspor dibuat.');
        }
        $bytes = match ($export->format) {
            ReportFormat::Pdf => $pdf->render($document),
            ReportFormat::Excel => $excel->render($document),
        };
        if ($bytes === '') {
            throw new RuntimeException('Renderer mengembalikan file kosong.');
        }

        $diskName = (string) config('report_exports.disk');
        $path = 'reports/'.$export->uuid.'.'.$export->format->extension();
        $fileName = $export->report_type->value.'-'
            .$export->from_date->toDateString().'-'
            .$export->to_date->toDateString().'-'.$export->uuid
            .'.'.$export->format->extension();
        if (! Storage::disk($diskName)->put($path, $bytes)) {
            throw new RuntimeException('Gagal menyimpan file laporan.');
        }

        DB::transaction(function () use ($export, $diskName, $path, $fileName, $bytes, $scopeHash): void {
            $locked = ReportExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($locked->status !== ReportExportStatus::Processing) {
                return;
            }
            $locked->forceFill([
                'status' => ReportExportStatus::Ready,
                'storage_disk' => $diskName,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_size' => strlen($bytes),
                'scope_hash' => $scopeHash,
                'failure_reason' => null,
                'completed_at' => now(),
                'expires_at' => now()->addHours(max(1, (int) config('report_exports.retention_hours', 48))),
            ])->save();
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Queued report export failed', [
            'report_export_id' => $this->exportId,
            'exception' => $exception,
        ]);

        $export = DB::transaction(function (): ?ReportExport {
            $locked = ReportExport::query()->lockForUpdate()->find($this->exportId);
            if (! $locked || $locked->status === ReportExportStatus::Ready
                || $locked->status === ReportExportStatus::Expired) {
                return null;
            }
            $locked->forceFill([
                'status' => ReportExportStatus::Failed,
                'failure_reason' => 'Generasi ekspor gagal; cek log server.',
                'failed_at' => now(),
                'expires_at' => null,
            ])->save();
            return $locked;
        }, 3);

        if ($export !== null) {
            // A previous attempt may have written bytes before a database error.
            $disk = $export->storage_disk ?: (string) config('report_exports.disk');
            Storage::disk($disk)->delete('reports/'.$export->uuid.'.'.$export->format->extension());
        }
    }
}
