<?php

namespace App\Enums\Reporting;

enum ReportExportStatus: string
{
    case Queued = 'QUEUED';
    case Processing = 'PROCESSING';
    case Ready = 'READY';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
}
