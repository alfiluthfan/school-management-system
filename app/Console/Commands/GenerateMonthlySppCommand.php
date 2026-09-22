<?php

namespace App\Console\Commands;

use App\Services\Spp\MonthlySppGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class GenerateMonthlySppCommand extends Command
{
    protected $signature = 'spp:generate-monthly {--month= : Periode YYYY-MM; default bulan sekarang} {--dry-run : Hanya simulasi, tidak membuat tagihan}';
    protected $description = 'Membuat satu tagihan SPP bulanan bagi setiap siswa aktif yang terdaftar dalam tahun ajaran aktif.';

    public function handle(MonthlySppGenerator $generator): int
    {
        $input = $this->option('month');
        if ($input !== null && (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input)
            || CarbonImmutable::createFromFormat('!Y-m', $input) === false)) {
            $this->error('Gunakan format --month=YYYY-MM.');
            return self::FAILURE;
        }
        try {
            $date = $input ? CarbonImmutable::createFromFormat('!Y-m', $input, 'Asia/Jakarta')
                : CarbonImmutable::now('Asia/Jakarta');
            $stats = $generator->generate($date, (bool) $this->option('dry-run'));
            $this->info(sprintf('%s %04d-%02d: eligible=%d created=%d existing=%d',
                $this->option('dry-run') ? 'DRY RUN' : 'SPP', $stats['year'], $stats['month'],
                $stats['eligible'], $stats['created'], $stats['existing']));
            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error(implode(' ', $e->validator->errors()->all()));
            return self::FAILURE;
        }
    }
}
