<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Reporting\ReportExportStatus;
use App\Jobs\Reporting\GenerateReportExport;
use App\Models\Reporting\ReportExport;
use App\Services\Reporting\ExcelReportRenderer;
use App\Services\Reporting\PdfReportRenderer;
use App\Services\Reporting\ReportExportAccess;
use App\Services\Reporting\ReportExportDocumentFactory;
use App\Services\Reporting\ReportExportScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ReportExportHttpTest extends HttpApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Jakarta', 'report_exports.disk' => 'report_exports',
            'report_exports.queue' => 'exports']);
        CarbonImmutable::setTestNow('2026-09-21 10:00:00');
        Storage::fake('report_exports');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function payload(string $format = 'pdf'): array
    {
        return ['report_type' => 'spp', 'format' => $format,
            'from' => '2026-09-01', 'to' => '2026-09-30'];
    }

    private function submit($actor, string $format = 'pdf'): ReportExport
    {
        $response = $this->actingAs($actor)->postJson(
            route('api.v1.report-exports.store'), $this->payload($format)
        )->assertStatus(202)->assertJsonPath('data.status', 'QUEUED');
        return ReportExport::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    }

    private function work(ReportExport $export): void
    {
        (new GenerateReportExport($export->id))->handle(
            app(ReportExportDocumentFactory::class),
            app(ReportExportAccess::class),
            app(ReportExportScope::class),
            app(PdfReportRenderer::class),
            app(ExcelReportRenderer::class)
        );
    }

    public function test_guest_cannot_request_export(): void
    {
        $this->postJson(route('api.v1.report-exports.store'), $this->payload())
            ->assertUnauthorized();
    }

    public function test_student_and_teacher_without_export_format_permissions_cannot_request(): void
    {
        [$student] = $this->createApiStudent();
        [$teacher] = $this->createApiTeacher();
        $this->actingAs($student)->postJson(
            route('api.v1.report-exports.store'), $this->payload()
        )->assertForbidden();
        $this->actingAs($teacher)->postJson(
            route('api.v1.report-exports.store'), [
                ...$this->payload(), 'report_type' => 'student_attendance',
            ]
        )->assertForbidden();
        $this->assertDatabaseCount('report_exports', 0);
        Queue::assertNothingPushed();
    }

    public function test_principal_requests_report_and_job_is_queued_without_file_generation(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $this->assertSame(ReportExportStatus::Queued, $export->status);
        $this->assertNull($export->file_path);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $principal->id, 'module' => 'report',
            'action' => 'EXPORT_REQUESTED', 'entity_id' => $export->id,
        ]);
        Queue::assertPushed(GenerateReportExport::class,
            fn (GenerateReportExport $job) => $job->exportId === $export->id
                && $job->queue === 'exports');
    }

    public function test_invalid_report_type_format_filters_and_range_are_rejected(): void
    {
        $principal = $this->createApiPrincipal();
        $this->actingAs($principal)->postJson(route('api.v1.report-exports.store'), [
            ...$this->payload(), 'report_type' => 'untrusted_query', 'format' => 'csv',
        ])->assertUnprocessable()->assertJsonValidationErrors(['report_type', 'format']);
        $this->actingAs($principal)->postJson(route('api.v1.report-exports.store'), [
            ...$this->payload(), 'class_uuid' => 'e567d335-925a-4f07-81db-a329ab37eed4',
        ])->assertUnprocessable()->assertJsonValidationErrors('class_uuid');
        $this->actingAs($principal)->postJson(route('api.v1.report-exports.store'), [
            ...$this->payload(), 'from' => '2024-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->assertDatabaseCount('report_exports', 0);
    }

    public function test_other_user_cannot_read_list_or_download_an_export(): void
    {
        $owner = $this->createApiPrincipal();
        $other = $this->createApiAdmin();
        $export = $this->submit($owner);
        $this->actingAs($other)->getJson(route('api.v1.report-exports.show', $export))
            ->assertNotFound();
        $this->actingAs($other)->get(route('api.v1.report-exports.download', $export))
            ->assertNotFound();
        $this->actingAs($other)->getJson(route('api.v1.report-exports.index'))
            ->assertOk()->assertJsonMissing(['uuid' => $export->uuid]);
        $this->actingAs($owner)->getJson(route('api.v1.report-exports.index'))
            ->assertOk()->assertJsonFragment(['uuid' => $export->uuid]);
    }

    public function test_download_before_ready_returns_conflict(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $this->actingAs($principal)->get(route('api.v1.report-exports.download', $export))
            ->assertStatus(409);
    }

    public function test_worker_generates_private_pdf_and_download_requires_owner(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $this->work($export);
        $export->refresh();
        $this->assertSame(ReportExportStatus::Ready, $export->status);
        $this->assertNotNull($export->expires_at);
        Storage::disk('report_exports')->assertExists($export->file_path);
        $this->assertSame('%PDF', substr(Storage::disk('report_exports')->get($export->file_path), 0, 4));
        $this->actingAs($principal)->getJson(route('api.v1.report-exports.show', $export))
            ->assertOk()->assertJsonPath('data.status', 'READY');
        $this->actingAs($principal)->get(route('api.v1.report-exports.download', $export))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'report', 'action' => 'EXPORT_DOWNLOADED', 'entity_id' => $export->id,
        ]);
    }

    public function test_worker_generates_valid_excel_and_reprocessing_is_idempotent(): void
    {
        $admin = $this->createApiAdmin();
        $export = $this->submit($admin, 'excel');
        $this->work($export);
        $export->refresh();
        $this->assertSame(ReportExportStatus::Ready, $export->status);
        $bytes = Storage::disk('report_exports')->get($export->file_path);
        $this->assertSame('PK', substr($bytes, 0, 2));
        $this->work($export);
        $this->assertDatabaseCount('report_exports', 1);
        $this->assertSame($bytes, Storage::disk('report_exports')->get($export->file_path));
    }

    public function test_download_is_blocked_if_scope_fingerprint_changes(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $this->work($export);
        $export->refresh();
        $export->forceFill(['scope_hash' => str_repeat('0', 64)])->save();
        $this->actingAs($principal)->get(route('api.v1.report-exports.download', $export))
            ->assertForbidden();
    }

    public function test_expired_file_is_not_downloadable_and_prune_retains_metadata(): void
    {
        $principal = $this->createApiPrincipal();
        $export = $this->submit($principal);
        $this->work($export);
        $export->refresh();
        $path = $export->file_path;
        $export->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($principal)->get(route('api.v1.report-exports.download', $export))
            ->assertStatus(410);
        $this->artisan('reports:prune-exports')->assertExitCode(0);
        Storage::disk('report_exports')->assertMissing($path);
        $this->assertSame(ReportExportStatus::Expired, $export->fresh()->status);
        $this->assertDatabaseHas('report_exports', ['uuid' => $export->uuid]);
    }
}
