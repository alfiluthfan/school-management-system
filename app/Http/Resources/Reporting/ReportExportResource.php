<?php

namespace App\Http\Resources\Reporting;

use App\Enums\Reporting\ReportExportStatus;
use App\Services\Reporting\ReportExportAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ready = $this->status === ReportExportStatus::Ready
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && app(ReportExportAccess::class)->canDownload(
                $request->user(), $this->resource
            );

        return [
            'uuid' => $this->uuid,
            'report_type' => $this->report_type->value,
            'format' => $this->format->value,
            'status' => $this->status->value,
            'period' => [
                'from' => $this->from_date->toDateString(),
                'to' => $this->to_date->toDateString(),
            ],
            'filters' => $this->filters,
            'file_name' => $ready ? $this->file_name : null,
            'file_size' => $ready ? $this->file_size : null,
            'download_url' => $ready
                ? route('api.v1.report-exports.download', $this->resource)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            // No storage path, disk name, internal error or bigint IDs.
        ];
    }
}
