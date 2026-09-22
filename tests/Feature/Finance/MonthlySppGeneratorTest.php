<?php

namespace Tests\Feature\Finance;

use App\Models\Finance\SppBill;
use App\Services\Spp\MonthlySppGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class MonthlySppGeneratorTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['school-finance.monthly_spp_amount' => '175000.00', 'school-finance.spp_due_day' => 10]);
    }

    private function readySchool(): array
    {
        $year = $this->createApiAcademicYear();
        $class = $this->createApiClass($year);
        [, $student] = $this->createApiStudent();
        $this->enrollApiStudent($student, $class);
        return [$year, $student];
    }

    public function test_generates_uniform_bill_for_enrolled_active_students(): void
    {
        [$year, $student] = $this->readySchool();
        $stats = app(MonthlySppGenerator::class)->generate(CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(1, $stats['eligible']);
        $this->assertSame(1, $stats['created']);
        $bill = SppBill::query()->firstOrFail();
        $this->assertSame($student->id, $bill->student_id);
        $this->assertSame($year->id, $bill->academic_year_id);
        $this->assertSame('175000.00', $bill->amount);
        $this->assertSame('0.00', $bill->paid_amount);
        $this->assertSame('2026-09-10', $bill->due_date->toDateString());
    }

    public function test_same_month_is_idempotent_and_does_not_change_paid_bill(): void
    {
        $this->readySchool();
        $generator = app(MonthlySppGenerator::class);
        $generator->generate(CarbonImmutable::parse('2026-09-01'));
        $bill = SppBill::query()->firstOrFail();
        $bill->update(['paid_amount' => '175000.00', 'status' => 'PAID']);
        $stats = $generator->generate(CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(0, $stats['created']);
        $this->assertSame(1, $stats['existing']);
        $this->assertDatabaseCount('spp_bills', 1);
        $this->assertSame('175000.00', $bill->fresh()->paid_amount);
    }

    public function test_dry_run_never_creates_bill_and_command_runs(): void
    {
        $this->readySchool();
        $this->artisan('spp:generate-monthly', ['--month' => '2026-09', '--dry-run' => true])
            ->assertSuccessful();
        $this->assertDatabaseCount('spp_bills', 0);
        $this->artisan('spp:generate-monthly', ['--month' => '2026-09'])->assertSuccessful();
        $this->assertDatabaseCount('spp_bills', 1);
    }

    public function test_missing_price_fails_closed_without_creating_bills(): void
    {
        $this->readySchool();
        config(['school-finance.monthly_spp_amount' => null]);
        $this->expectException(ValidationException::class);
        try {
            app(MonthlySppGenerator::class)->generate(CarbonImmutable::parse('2026-09-01'));
        } finally {
            $this->assertDatabaseCount('spp_bills', 0);
        }
    }

    public function test_active_student_without_enrollment_is_billed_but_inactive_is_not(): void
    {
        $this->createApiAcademicYear();
        [, $active] = $this->createApiStudent();
        [, $inactive] = $this->createApiStudent();
        $inactive->update(['status' => 'INACTIVE']);
        $stats = app(MonthlySppGenerator::class)->generate(CarbonImmutable::parse('2026-09-01'));
        $this->assertSame(1, $stats['eligible']);
        $this->assertDatabaseCount('spp_bills', 1);
        $this->assertSame($active->id, SppBill::query()->firstOrFail()->student_id);
        $this->artisan('spp:generate-monthly', ['--month' => '2025-01'])->assertFailed();
        $this->artisan('spp:generate-monthly', ['--month' => 'nope'])->assertFailed();
    }
}
