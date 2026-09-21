<?php

namespace App\Console\Commands;

use App\Enums\Reporting\ReportExportStatus;
use App\Models\Reporting\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class PruneReportExports extends Command
{
    protected $signature = 'reports:prune-exports';
    protected $description = 'Delete expired private report files, retaining their metadata';

    public function handle(): int
    {
        $count = 0;
        ReportExport::query()->where('status', ReportExportStatus::Ready->value)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    $disk = Storage::disk($export->storage_disk);
                    if ($export->file_path && $disk->exists($export->file_path)) {
                        // Throws on filesystem failure if the disk has 'throw' => true.
                        if (! $disk->delete($export->file_path)) {
                            throw new \RuntimeException('Gagal menghapus ekspor '.$export->uuid);
                        }
                    }
                    $export->forceFill([
                        'status' => ReportExportStatus::Expired,
                        'file_path' => null,
                    ])->save();
                    $count++;
                }
            });
        $this->info('Expired exports pruned: '.$count);
        return self::SUCCESS;
    }
}
