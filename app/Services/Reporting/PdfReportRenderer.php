<?php

namespace App\Services\Reporting;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfReportRenderer
{
    public function render(array $document): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        // Blade escapes all values, including class names and user names.
        $pdf->loadHtml(view('reports.export', compact('document'))->render(), 'UTF-8');
        $pdf->render();
        return $pdf->output();
    }
}
