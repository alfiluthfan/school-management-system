<?php

namespace App\Services\Spp;

use App\Enums\Finance\SppBillStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Student;
use App\Models\Finance\SppBill;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class MonthlySppGenerator
{
    /** @return array{year: int, month: int, eligible: int, created: int, existing: int} */
    public function generate(CarbonImmutable $date, bool $dryRun = false): array
    {
        $period = $date->startOfMonth();
        $amount = config('school-finance.monthly_spp_amount');
        $dueDay = config('school-finance.spp_due_day');
        if (! is_string($amount) && ! is_numeric($amount)) {
            throw ValidationException::withMessages(['amount' => '150000.00']);
        }
        $amount = (string) $amount;
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal SPP harus positif dan maksimal dua desimal.']);
        }
        if (! is_int($dueDay) || $dueDay < 1 || $dueDay > 28) {
            throw ValidationException::withMessages(['due_day' => 'Jatuh tempo harus tanggal 1 sampai 28.']);
        }
        $years = AcademicYear::query()->where('is_active', true)
            ->whereDate('start_date', '<=', $period->toDateString())
            ->whereDate('end_date', '>=', $period->toDateString())->get();
        if ($years->count() !== 1) {
            throw ValidationException::withMessages(['period' => 'Periode harus berada dalam tepat satu tahun ajaran aktif.']);
        }
        $year = $years->first();
        $result = ['year' => $period->year, 'month' => $period->month, 'eligible' => 0, 'created' => 0, 'existing' => 0];
        // Business rule: every ACTIVE student is billed; enrollment and portal login
        // are NOT prerequisites for an administrative tuition obligation.
        $students = Student::query()->where('status', 'ACTIVE')->orderBy('id')->select('id');
        // A database unique constraint makes concurrent commands safe as well as sequential reruns.
        $students->chunkById(100, function ($chunk) use (&$result, $period, $year, $amount, $dueDay, $dryRun): void {
            foreach ($chunk as $student) {
                $result['eligible']++;
                $identity = ['student_id' => $student->id, 'billing_year' => $period->year, 'billing_month' => $period->month];
                if ($dryRun) {
                    $exists = SppBill::query()->where($identity)->exists();
                    $result[$exists ? 'existing' : 'created']++;
                    continue;
                }
                $bill = SppBill::query()->createOrFirst($identity, [
                    'bill_number' => 'SPP-'.$period->format('Ym').'-'.$student->id,
                    'academic_year_id' => $year->id,
                    'amount' => $amount,
                    'paid_amount' => '0.00',
                    'due_date' => $period->day($dueDay)->toDateString(),
                    'status' => SppBillStatus::Pending->value,
                    'notes' => null,
                ]);
                $result[$bill->wasRecentlyCreated ? 'created' : 'existing']++;
            }
        });
        return $result;
    }
}
