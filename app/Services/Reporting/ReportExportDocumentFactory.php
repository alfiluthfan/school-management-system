<?php

namespace App\Services\Reporting;

use App\Enums\Reporting\ReportType;
use App\Models\Auth\User;
use App\Models\Reporting\ReportExport;
use App\Queries\Reports\SavingsReportQuery;
use App\Queries\Reports\SppReportQuery;
use App\Queries\Reports\StudentAttendanceReportQuery;
use App\Queries\Reports\TeacherAttendanceReportQuery;
use Carbon\CarbonImmutable;

/**
 * Builds one presentation-neutral document from the SAME scoped JSON queries.
 * Only known server-defined headings and columns are rendered.
 */
final class ReportExportDocumentFactory
{
    public function __construct(
        private readonly StudentAttendanceReportQuery $students,
        private readonly TeacherAttendanceReportQuery $teachers,
        private readonly SavingsReportQuery $savings,
        private readonly SppReportQuery $spp
    ) {}

    public function build(ReportExport $export, User $user): array
    {
        $from = CarbonImmutable::parse(
            $export->from_date->toDateString(), config('app.timezone')
        )->startOfDay();
        $to = CarbonImmutable::parse(
            $export->to_date->toDateString(), config('app.timezone')
        )->endOfDay();
        $filters = $export->filters ?? [];

        $data = match ($export->report_type) {
            ReportType::StudentAttendance => $this->students->execute(
                $user, $from, $to, $filters['class_uuid'] ?? null
            ),
            ReportType::TeacherAttendance => $this->teachers->execute(
                $user, $from, $to, $filters['teacher_uuid'] ?? null
            ),
            ReportType::Savings => $this->savings->execute($user, $from, $to),
            ReportType::Spp => $this->spp->execute($user, $from, $to),
        };

        $sections = match ($export->report_type) {
            ReportType::StudentAttendance => $this->studentSections($data),
            ReportType::TeacherAttendance => $this->teacherSections($data),
            ReportType::Savings => $this->savingsSections($data),
            ReportType::Spp => $this->sppSections($data),
        };

        return [
            'title' => $export->report_type->title(),
            'uuid' => $export->uuid,
            'period' => $from->toDateString().' s.d. '.$to->toDateString(),
            'generated_at' => CarbonImmutable::now(config('app.timezone'))
                ->format('Y-m-d H:i:s T'),
            'requester' => $user->name,
            'filters' => collect($filters)->map(
                fn ($value, $key) => $key.': '.$value
            )->implode(' | ') ?: 'Tidak ada',
            'sections' => $sections,
            'note' => match ($export->report_type) {
                ReportType::Savings => 'Saldo merupakan snapshot saat file dibuat; rincian transaksi memakai tanggal transaksi dan hanya status POSTED.',
                ReportType::Spp => 'Kohort tagihan berdasarkan jatuh tempo; penerimaan berdasarkan tanggal pembayaran POSTED. Angka mencerminkan kondisi data saat file dibuat.',
                default => 'Agregasi berdasarkan record absensi dalam periode; bukan tingkat kehadiran terhadap seluruh siswa/guru terdaftar.',
            },
        ];
    }

    private function section(string $title, array $headers, array $rows): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    }

    private function studentSections(array $d): array
    {
        $s = $d['summary'];
        return [
            $this->section('Ringkasan', ['Metrik', 'Nilai'], [
                ['Jumlah record', $s['total_records']], ['Hadir', $s['present']],
                ['Terlambat', $s['late']], ['Sakit', $s['sick']],
                ['Izin', $s['permission']], ['Alpa', $s['absent']],
                ['Total menit terlambat', $s['late_minutes']['total']],
                ['Rata-rata menit terlambat', $s['late_minutes']['average']],
                ['Maksimum menit terlambat', $s['late_minutes']['maximum']],
            ]),
            $this->section('Per Hari', ['Tanggal','Record','Hadir','Terlambat','Sakit','Izin','Alpa'],
                array_map(fn ($r) => [$r['date'],$r['total'],$r['present'],$r['late'],$r['sick'],$r['permission'],$r['absent']], $d['daily'])),
            $this->section('Per Kelas', ['Kelas','Kode','Record','Hadir','Terlambat','Alpa'],
                array_map(fn ($r) => [$r['class']['name'] ?? '-', $r['class']['code'] ?? '-',
                    $r['total'],$r['present'],$r['late'],$r['absent']], $d['classes'])),
        ];
    }

    private function teacherSections(array $d): array
    {
        $s = $d['summary'];
        return [
            $this->section('Ringkasan', ['Metrik','Nilai'], [
                ['Jumlah record', $s['total_records']],['Hadir',$s['present']],
                ['Terlambat',$s['late']],['Sakit',$s['sick']],['Izin',$s['permission']],
                ['Alpa',$s['absent']],['Total menit terlambat',$s['late_minutes']['total']],
                ['Rata-rata menit terlambat',$s['late_minutes']['average']],
            ]),
            $this->section('Per Hari', ['Tanggal','Record','Hadir','Terlambat','Alpa'],
                array_map(fn ($r) => [$r['date'],$r['total'],$r['present'],$r['late'],$r['absent']],$d['daily'])),
            $this->section('Per Guru', ['Guru','NIP','Record','Hadir','Terlambat','Menit Terlambat'],
                array_map(fn ($r) => [$r['teacher']['name'] ?? '-', $r['teacher']['nip'] ?? '-',
                    $r['total'],$r['present'],$r['late'],$r['late_minutes']],$d['teachers'])),
        ];
    }

    private function savingsSections(array $d): array
    {
        $t = $d['transactions'];
        return [
            $this->section('Snapshot Saldo', ['Metrik','Nilai'], [
                ['Jumlah rekening', $d['snapshot']['accounts']],
                ['Total saldo', $d['snapshot']['total_balance']],
            ]),
            $this->section('Transaksi POSTED', ['Jenis','Jumlah transaksi','Total nominal'], [
                ['Deposit',$t['deposit']['count'],$t['deposit']['amount']],
                ['Withdrawal',$t['withdrawal']['count'],$t['withdrawal']['amount']],
                ['Reversal',$t['reversal']['count'],$t['reversal']['amount']],
                ['Adjustment',$t['adjustment']['count'],$t['adjustment']['amount']],
            ]),
            $this->section('Per Hari', ['Tanggal','Transaksi','Deposit','Withdrawal','Reversal'],
                array_map(fn ($r) => [$r['date'],$r['transaction_count'],$r['deposits'],
                    $r['withdrawals'],$r['reversals']],$d['daily'])),
        ];
    }

    private function sppSections(array $d): array
    {
        $b = $d['bills']; $c = $d['collections'];
        return [
            $this->section('Kohort Tagihan (tanggal jatuh tempo)', ['Metrik','Nilai'], [
                ['Jumlah tagihan',$b['count']],['Pending',$b['pending']],['Partial',$b['partial']],
                ['Paid',$b['paid']],['Overdue',$b['overdue']],['Cancelled',$b['cancelled']],
                ['Total tagihan',$b['billed_amount']],['Total dibayar',$b['paid_amount']],
                ['Sisa tagihan',$b['outstanding_amount']],
                ['Rasio penagihan (%)',$b['collection_ratio_percent']],
            ]),
            $this->section('Penerimaan POSTED (tanggal pembayaran)', ['Metrik','Nilai'], [
                ['Jumlah pembayaran',$c['payment_count']], ['Nominal pembayaran',$c['amount']],
            ]),
            $this->section('Penerimaan Per Hari', ['Tanggal','Pembayaran','Nominal'],
                array_map(fn ($r) => [$r['date'],$r['payment_count'],$r['amount']],$c['daily'])),
        ];
    }
}
