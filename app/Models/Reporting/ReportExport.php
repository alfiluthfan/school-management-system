<?php

namespace App\Models\Reporting;

use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use App\Models\Auth\User;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'requested_by', 'report_type', 'format', 'from_date', 'to_date',
        'filters', 'status', 'storage_disk', 'file_path', 'file_name',
        'file_size', 'scope_hash', 'failure_reason', 'started_at', 'completed_at',
        'failed_at', 'expires_at',
    ];

    protected $hidden = ['storage_disk', 'file_path', 'scope_hash', 'failure_reason'];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'format' => ReportFormat::class,
            'status' => ReportExportStatus::class,
            'filters' => 'array',
            'from_date' => 'date',
            'to_date' => 'date',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
