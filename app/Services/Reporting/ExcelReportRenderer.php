<?php

namespace App\Services\Reporting;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ExcelReportRenderer
{
    public function render(array $document): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Laporan');
        $this->cell($sheet, 1, 1, $document['title']);
        $this->cell($sheet, 2, 1, 'Periode: '.$document['period']);
        $this->cell($sheet, 3, 1, 'Dibuat: '.$document['generated_at']);
        $this->cell($sheet, 4, 1, 'Pemohon: '.$document['requester']);
        $this->cell($sheet, 5, 1, 'Filter: '.$document['filters']);
        $this->cell($sheet, 6, 1, 'Catatan: '.$document['note']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)
            ->getColor()->setRGB('173E67');
        $sheet->freezePane('A9');

        $row = 8;
        $maxColumns = 2;
        foreach ($document['sections'] as $section) {
            $this->cell($sheet, $row, 1, $section['title']);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
            $row++;
            $maxColumns = max($maxColumns, count($section['headers']));
            foreach ($section['headers'] as $i => $heading) {
                $this->cell($sheet, $row, $i + 1, $heading);
            }
            $end = Coordinate::stringFromColumnIndex(count($section['headers']));
            $sheet->getStyle('A'.$row.':'.$end.$row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '173E67']],
            ]);
            $row++;
            if ($section['rows'] === []) {
                $this->cell($sheet, $row++, 1, 'Tidak ada data');
            } else {
                foreach ($section['rows'] as $line) {
                    foreach ($line as $i => $value) {
                        $this->cell($sheet, $row, $i + 1, $value);
                    }
                    $row++;
                }
            }
            $row += 2;
        }
        for ($col = 1; $col <= $maxColumns; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letter)->setWidth($col === 1 ? 32 : 22);
        }
        $sheet->getPageSetup()->setOrientation('landscape');

        $temp = tempnam(sys_get_temp_dir(), 'sms-report-');
        if ($temp === false) {
            $book->disconnectWorksheets();
            throw new RuntimeException('Tidak dapat membuat file sementara.');
        }
        try {
            (new Xlsx($book))->save($temp);
            $content = file_get_contents($temp);
            if ($content === false) {
                throw new RuntimeException('Tidak dapat membaca workbook.');
            }
            return $content;
        } finally {
            $book->disconnectWorksheets();
            @unlink($temp);
        }
    }

    private function cell($sheet, int $row, int $column, mixed $value): void
    {
        $coordinate = Coordinate::stringFromColumnIndex($column).$row;
        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue($coordinate, $value);
        } else {
            // Prevent spreadsheet-formula injection; keep money as exact decimal text.
            $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
        }
    }
}
