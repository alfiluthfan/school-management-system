<?php

namespace App\Services\Reporting;

use App\Models\Auth\User;
use App\Models\Reporting\ReportExport;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use Illuminate\Support\Facades\Gate;

final class ReportExportAccess
{
    public function allows(User $user, ReportType $type, ReportFormat $format): bool
    {
        return $user->is_active
            && Gate::forUser($user)->allows($type->permission())
            && Gate::forUser($user)->allows($format->permission());
    }

    public function __construct(private readonly ReportExportScope $scope) {}

    public function canDownload(User $user, ReportExport $export): bool
    {
        if ($export->requested_by !== $user->id
            || ! $this->allows($user, $export->report_type, $export->format)) {
            return false;
        }

        // A finished export must not remain downloadable after class/child scope changes.
        return $export->status !== \App\Enums\Reporting\ReportExportStatus::Ready
            || ($export->scope_hash !== null && hash_equals(
                $export->scope_hash, $this->scope->hash($user, $export->report_type)
            ));
    }
}
