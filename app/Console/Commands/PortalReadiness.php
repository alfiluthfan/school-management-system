<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Non-destructive preflight. No secret values or export file contents are printed. */
final class PortalReadiness extends Command
{
    protected $signature = 'portal:readiness {--production : Enforce deployment-only checks} {--services : Probe database and configured queue/storage}';
    protected $description = 'Verify portal security and report export deployment prerequisites.';

    public function handle(): int
    {
        $errors = 0;
        $production = (bool) $this->option('production');
        $check = function (bool $ok, string $message) use (&$errors): void {
            $this->line(($ok ? '[PASS] ' : '[FAIL] ').$message);
            if (! $ok) $errors++;
        };

        $check((bool) config('app.key'), 'Application key configured');
        $check(config('filesystems.disks.'.config('report_exports.disk')) !== null,
            'Report export disk is configured');
        $disk = (string) config('report_exports.disk');
        $definition = (array) config('filesystems.disks.'.$disk, []);
        $root = str_replace('\\', '/', (string) ($definition['root'] ?? ''));
        $public = str_replace('\\', '/', public_path());
        $check(($definition['visibility'] ?? null) === 'private'
            && empty($definition['url'])
            && ($root === '' || (! str_starts_with(strtolower($root), strtolower($public.'/')) && strtolower($root) !== strtolower($public))),
            'Export disk is not configured as publicly exposed (verify ACL manually for remote disks)');
        $check((string) config('report_exports.queue') !== '', 'Export queue name is configured');
        $check((int) config('report_exports.retention_hours', 48) > 0, 'Export retention is positive');
        $check(config('queue.default') !== 'sync' || ! $production,
            'Production jobs are asynchronous (sync allowed only for local tests)');
        $retry = (int) config('queue.connections.redis.retry_after', 0);
        if ($production || config('queue.default') === 'redis') {
            $check($retry > 180, 'Redis retry_after exceeds export job timeout 180s');
        }
        if ($production) {
            if (version_compare(app()->version(), '13.0.0', '>=')) {
                $check(PHP_VERSION_ID >= 80300, 'Laravel 13 requires PHP 8.3+');
            }
            $check(app()->environment('production'), 'APP_ENV is production');
            $check(config('app.debug') === false, 'APP_DEBUG is false');
            $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS');
            $check(config('session.secure') === true, 'Session cookie Secure is enabled');
            $check(in_array(config('session.driver'), ['database', 'redis'], true),
                'Session driver is shared (database or Redis)');
            $check(app()->configurationIsCached(), 'Configuration is cached');
            $check(config('queue.default') === 'redis', 'Default queue uses Redis');
        }
        if ($this->option('services') || $production) {
            try {
                DB::select('SELECT 1');
                $check(Schema::hasColumn('users', 'portal_session_version'),
                    'Database accessible and portal session migration applied');
            } catch (Throwable $e) {
                $check(false, 'Database check failed (inspect server log; details intentionally hidden)');
            }
            try {
                // A queue size probe checks connectivity, NOT worker liveness.
                Queue::connection('redis')->size((string) config('report_exports.queue'));
                $check(true, 'Redis queue readable (worker health still requires separate probe)');
            } catch (Throwable $e) {
                $check(false, 'Redis queue probe failed');
            }
            try {
                Storage::disk($disk)->exists('.phase8-readiness-probe');
                $check(true, 'Export storage adapter is readable (write and download still require smoke test)');
            } catch (Throwable $e) {
                $check(false, 'Export storage probe failed');
            }
        }
        if ($errors > 0) {
            $this->error("Preflight found {$errors} failing check(s). See support/production-readiness-checklist.md.");
            return self::FAILURE;
        }
        $this->info('Preflight checks passed; this is NOT proof of production readiness or worker health.');
        return self::SUCCESS;
    }
}
