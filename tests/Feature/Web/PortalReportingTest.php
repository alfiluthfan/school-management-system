<?php

namespace Tests\Feature\Web;

use App\Enums\Reporting\ReportExportStatus;
use App\Enums\Reporting\ReportFormat;
use App\Enums\Reporting\ReportType;
use App\Jobs\Reporting\GenerateReportExport;
use App\Models\Auth\User;
use App\Models\Reporting\ReportExport;
use App\Services\Reporting\ReportExportScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\HttpApiTestCase;

final class PortalReportingTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['report_exports.disk' => 'report_exports', 'report_exports.queue' => 'exports']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-21 10:00:00', 'Asia/Jakarta'));
        Storage::fake('report_exports');
        Queue::fake();
    }

    private function inertiaGet(string $url)
    {
        // Match the active Inertia asset version; avoid false 409 test failures.
        $initial = $this->get($url)->assertOk()->assertViewHas('page');
        $version = data_get($initial->viewData('page'), 'version');
        return $this->get($url, [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version ?? '',
        ]);
    }

    private function payload(string $format = 'pdf', string $type = 'spp'): array
    {
        return ['report_type' => $type, 'format' => $format,
            'from' => '2026-09-01', 'to' => '2026-09-30'];
    }

    private function submit(User $user, string $format = 'pdf', string $type = 'spp'): ReportExport
    {
        $this->actingAs($user, 'web')->post('/reports/exports', $this->payload($format, $type))
            ->assertRedirect()->assertSessionHas('success');
        return ReportExport::query()->latest('id')->firstOrFail();
    }

    public function test_guest_redirects_and_user_without_report_permissions_gets_403(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->post('/reports/exports', $this->payload())->assertRedirect('/login');
        $user = User::query()->create([
            'name' => 'No Reports Access', 'username' => $this->httpToken('noreports'),
            'email' => $this->httpToken('noreports').'@example.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->actingAs($user, 'web')->get('/reports')->assertForbidden();
        $this->post('/reports/exports', $this->payload())->assertForbidden();
    }

    public function test_principal_sees_four_report_options_and_default_month(): void
    {
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/reports')->assertOk()
            ->assertJsonPath('component', 'Reports/Index')
            ->assertJsonPath('props.reporting.filters.from', '2026-09-01')
            ->assertJsonPath('props.reporting.filters.to', '2026-09-21')
            ->assertJsonCount(4, 'props.reporting.types')
            ->assertJsonCount(2, 'props.reporting.formats')
            ->assertJsonPath('props.report.summary.total_records', 0);
    }

    public function test_teacher_report_uses_homeroom_scope_and_foreign_class_filter_is_404(): void
    {
        [$user, $teacher] = $this->createApiTeacher();
        [, $anotherTeacher] = $this->createApiTeacher();
        [, $ownStudent] = $this->createApiStudent();
        [, $foreignStudent] = $this->createApiStudent();
        $year = $this->createApiAcademicYear();
        $ownClass = $this->createApiClass($year, $teacher);
        $foreignClass = $this->createApiClass($year, $anotherTeacher);
        $this->createApiAttendance($ownStudent, $ownClass);
        $this->createApiAttendance($foreignStudent, $foreignClass);
        $this->actingAs($user, 'web');
        $this->inertiaGet('/reports?report_type=student_attendance&from=2026-09-01&to=2026-09-30')
            ->assertJsonPath('props.report.summary.total_records', 1)
            ->assertJsonPath('props.reporting.can_export', false)
            ->assertJsonPath('props.reporting.options.classes.0.uuid', $ownClass->uuid)
            ->assertDontSee($foreignClass->uuid);
        $this->get('/reports?report_type=student_attendance&from=2026-09-01&to=2026-09-30&class_uuid='.$foreignClass->uuid)
            ->assertNotFound();
    }

    public function test_spp_report_cohort_matches_existing_query(): void
    {
        $principal = $this->createApiPrincipal();
        [, $student] = $this->createApiStudent();
        $this->createApiSppBill($student, $this->createApiAcademicYear(), '500000.00');
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/reports?report_type=spp&from=2026-09-01&to=2026-09-30')
            ->assertJsonPath('props.report.bills.count', 1)
            ->assertJsonPath('props.report.bills.outstanding_amount', '500000.00')
            ->assertJsonPath('props.report.collections.amount', '0.00');
    }

    public function test_invalid_range_and_irrelevant_filters_are_rejected(): void
    {
        $this->actingAs($this->createApiPrincipal(), 'web');
        $this->get('/reports?report_type=spp&from=2025-01-01&to=2026-09-21')
            ->assertSessionHasErrors('to');
        $this->get('/reports?report_type=invalid&from=2026-09-01&to=2026-09-21')
            ->assertSessionHasErrors('report_type');
        $this->get('/reports?report_type=spp&from=2026-09-01&to=2026-09-21&class_uuid=3f02b927-c4b3-4a7c-a68e-1944c278e4c0')
            ->assertSessionHasErrors('class_uuid');
        $this->get('/reports?report_type=spp&from=2026-09-01&to=2026-09-21&export_status=INVALID')
            ->assertSessionHasErrors('export_status');
    }

    public function test_teacher_without_export_format_permission_cannot_queue_even_if_report_is_visible(): void
    {
        [$teacher] = $this->createApiTeacher();
        $this->actingAs($teacher, 'web');
        $this->get('/reports?report_type=student_attendance&from=2026-09-01&to=2026-09-30')->assertOk();
        $this->post('/reports/exports', $this->payload('pdf', 'student_attendance'))
            ->assertForbidden();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('report_exports', 0);
    }

    public function test_principal_requests_export_using_existing_job_and_audit(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal, 'excel', 'spp');
        $this->assertSame(ReportExportStatus::Queued, $export->status);
        $this->assertNull($export->file_path);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'report', 'action' => 'EXPORT_REQUESTED',
            'user_id' => $principal->id, 'entity_id' => $export->id,
        ]);
        Queue::assertPushed(GenerateReportExport::class,
            fn (GenerateReportExport $job) => $job->exportId === $export->id && $job->queue === 'exports');
    }

    public function test_export_invalid_fields_do_not_queue_job(): void
    {
        $this->actingAs($this->createApiPrincipal(), 'web')->from('/reports')
            ->post('/reports/exports', [
                ...$this->payload(), 'format' => 'csv', 'class_uuid' => 'not-a-uuid',
            ])->assertRedirect('/reports')->assertSessionHasErrors(['format', 'class_uuid']);
        $this->assertDatabaseCount('report_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_other_user_cannot_list_status_or_download_owners_file(): void
    {
        $owner = $this->createApiPrincipal();
        $other = $this->createApiAdmin();
        $export = $this->submit($owner);
        $this->actingAs($other, 'web');
        $this->get('/reports/exports/'.$export->uuid.'/status')->assertNotFound();
        $this->get('/reports/exports/'.$export->uuid.'/download')->assertNotFound();
        $this->inertiaGet('/reports?report_type=spp&from=2026-09-01&to=2026-09-30')
            ->assertJsonPath('props.exports.total', 0)
            ->assertDontSee($export->uuid);
    }

    public function test_status_only_serializes_allowlisted_data_and_queued_download_is_409(): void
    {
        $owner = $this->createApiPrincipal();
        $export = $this->submit($owner);
        $this->actingAs($owner, 'web');
        $this->getJson('/reports/exports/'.$export->uuid.'/status')->assertOk()
            ->assertJsonPath('data.status', 'QUEUED')
            ->assertJsonPath('data.download_url', null)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.storage_disk')
            ->assertJsonMissingPath('data.file_path')
            ->assertJsonMissingPath('data.failure_reason')
            ->assertJsonMissingPath('data.scope_hash');
        $this->get('/reports/exports/'.$export->uuid.'/download')->assertStatus(409);
    }

    public function test_private_download_requires_valid_current_scope_and_unexpired_file(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $path = 'reports/test-portal.pdf';
        Storage::disk('report_exports')->put($path, '%PDF-1.4 test file');
        $export->forceFill([
            'status' => ReportExportStatus::Ready,
            'storage_disk' => 'report_exports', 'file_path' => $path,
            'file_name' => 'report-portal.pdf', 'file_size' => 18,
            'scope_hash' => app(ReportExportScope::class)->hash($principal, ReportType::Spp),
            'expires_at' => now()->addDay(),
        ])->save();
        $this->actingAs($principal, 'web');
        $this->getJson('/reports/exports/'.$export->uuid.'/status')->assertOk()
            ->assertJsonPath('data.download_url', route('portal.reports.exports.download', $export));
        $this->get('/reports/exports/'.$export->uuid.'/download')->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'report', 'action' => 'EXPORT_DOWNLOADED',
            'entity_id' => $export->id,
        ]);
        $export->forceFill(['scope_hash' => str_repeat('0', 64)])->save();
        $this->get('/reports/exports/'.$export->uuid.'/download')->assertForbidden();
        $export->forceFill([
            'scope_hash' => app(ReportExportScope::class)->hash($principal, ReportType::Spp),
            'expires_at' => now()->subMinute(),
        ])->save();
        $this->get('/reports/exports/'.$export->uuid.'/download')->assertStatus(410);
    }

    public function test_export_list_status_filter_only_returns_own_matching_items(): void
    {
        $principal = $this->createApiPrincipal();
        $queued = $this->submit($principal);
        $ready = $this->submit($principal, 'excel');
        $ready->forceFill(['status' => ReportExportStatus::Failed])->save();
        $this->actingAs($principal, 'web');
        $this->inertiaGet('/reports?report_type=spp&from=2026-09-01&to=2026-09-30&export_status=QUEUED')
            ->assertJsonPath('props.exports.total', 1)
            ->assertJsonPath('props.exports.data.0.uuid', $queued->uuid)
            ->assertDontSee($ready->uuid);
    }
}
