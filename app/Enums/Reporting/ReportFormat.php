<?php

namespace App\Enums\Reporting;

enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Excel = 'excel';

    public function permission(): string
    {
        return match ($this) {
            self::Pdf => 'report.export.pdf',
            self::Excel => 'report.export.excel',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Excel => 'xlsx',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
