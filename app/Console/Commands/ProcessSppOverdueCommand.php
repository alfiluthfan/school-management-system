<?php

namespace App\Console\Commands;

use App\Services\Spp\SppOverdueProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ProcessSppOverdueCommand extends Command
{
    protected $signature = 'spp:process-overdue
        {--date= : Tanggal proses YYYY-MM-DD}
        {--force-reminder : Abaikan cadence dan kirim reminder hari ini}';

    protected $description =
        'Tandai tagihan SPP overdue dan antrekan reminder WhatsApp';

    public function handle(
        SppOverdueProcessor $processor
    ): int {
        try {
            $asOf = $this->resolveAsOfDate();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = $processor->run(
            asOf: $asOf,
            forceReminder:
                (bool) $this->option('force-reminder')
        );

        $this->info(
            'SPP overdue processing selesai.'
        );

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned', $result->scanned],
                ['Marked overdue', $result->markedOverdue],
                ['Reminders created', $result->remindersCreated],
                [
                    'Delivery jobs dispatched',
                    $result->deliveryJobsDispatched,
                ],
                [
                    'Without eligible recipients',
                    $result->withoutRecipients,
                ],
                [
                    'Skipped by cadence',
                    $result->skippedByCadence,
                ],
            ]
        );

        return self::SUCCESS;
    }

    private function resolveAsOfDate(): CarbonImmutable
    {
        $raw = $this->option('date');

        if (! is_string($raw) || $raw === '') {
            return CarbonImmutable::now(
                config('app.timezone')
            );
        }

        try {
            $date = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $raw,
                config('app.timezone')
            );
        } catch (\Throwable) {
            throw new InvalidArgumentException(
                'Option --date harus berformat YYYY-MM-DD.'
            );
        }

        if (
            ! $date
            || $date->format('Y-m-d') !== $raw
        ) {
            throw new InvalidArgumentException(
                'Option --date harus berformat YYYY-MM-DD.'
            );
        }

        return $date;
    }
}
