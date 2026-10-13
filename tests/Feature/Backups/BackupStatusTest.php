<?php

use App\Domain\Alerts\Providers\BackupLate;
use App\Domain\Backups\BackupStatus;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    freezeToday('2026-01-31');
    $this->statusDir = sys_get_temp_dir().'/zb-backup-status-'.bin2hex(random_bytes(6));
    mkdir($this->statusDir);
    config(['zb.backup_status_dir' => $this->statusDir]);
});

afterEach(function () {
    foreach (glob($this->statusDir.'/*.json') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->statusDir);
});

/** Writes a status file the way infra/bin/zb-lib.sh does. */
function backupFile(string $dir, string $name, array $body): void
{
    file_put_contents("{$dir}/{$name}.json", json_encode($body)."\n");
}

function hoursAgo(float|int $hours): string
{
    return CarbonImmutable::now()->subMinutes((int) ($hours * 60))->utc()->format('Y-m-d\TH:i:s\Z');
}

function backupSuccess(string $dir, string $kind, string $finishedAt): void
{
    $body = $kind === 'db'
        ? ['kind' => 'db', 'result' => 'success', 'finished_at' => $finishedAt, 'file' => 'db-test.sql.age', 'bytes' => 5242880, 'sha256' => 'abc', 'copies' => 'hourly,daily']
        : ['kind' => 'files', 'result' => 'success', 'finished_at' => $finishedAt, 'file' => 'files-test.tar.age', 'bytes' => 1048576, 'chain' => '2026-01', 'type' => 'incremental'];
    backupFile($dir, "backup-{$kind}", $body);
    backupFile($dir, "backup-{$kind}-last-success", $body);
}

it('BR-OPS-07 fresh success shows OK', function () {
    backupSuccess($this->statusDir, 'db', hoursAgo(0.5));
    backupSuccess($this->statusDir, 'files', hoursAgo(20));

    $status = BackupStatus::read();

    expect($status['db']['configured'])->toBeTrue()
        ->and($status['db']['late'])->toBeFalse()
        ->and($status['db']['last_success']['size_mb'])->toBe('5.0')
        ->and($status['db']['last_success']['copies'])->toBe('hourly,daily')
        ->and($status['files']['late'])->toBeFalse()
        ->and($status['files']['last_success']['copies'])->toBe('incremental')
        ->and(app(BackupLate::class)->count(owner()))->toBe(0);

    $this->actingAs(owner())->get(route('backups.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('backups/show')->where('status.db.late', false)->where('status.db.last_attempt.result', 'success'));
});

it('BR-OPS-07 a success 3 hours old shows Late', function () {
    backupSuccess($this->statusDir, 'db', hoursAgo(3));
    backupSuccess($this->statusDir, 'files', hoursAgo(27));

    expect(BackupStatus::read()['db']['late'])->toBeTrue()
        ->and(BackupStatus::read()['files']['late'])->toBeTrue()
        ->and(app(BackupLate::class)->count(owner()))->toBe(2)
        ->and(app(BackupLate::class)->count(accountant()))->toBe(0);

    $this->actingAs(owner())->get(route('backups.show'))
        ->assertInertia(fn (Assert $page) => $page->where('status.db.late', true));
});

it('BR-OPS-07 a failure shows the error text', function () {
    backupSuccess($this->statusDir, 'db', hoursAgo(1));
    backupFile($this->statusDir, 'backup-db', ['kind' => 'db', 'result' => 'failure', 'finished_at' => hoursAgo(0.1), 'error' => 'upload to off-site storage failed']);

    $db = BackupStatus::read()['db'];

    expect($db['last_attempt'])->toMatchArray(['result' => 'failure', 'error' => 'upload to off-site storage failed'])
        ->and($db['late'])->toBeFalse();

    $this->actingAs(owner())->get(route('backups.show'))
        ->assertInertia(fn (Assert $page) => $page->where('status.db.last_attempt.error', 'upload to off-site storage failed'));
});

it('missing folder shows not configured', function () {
    config(['zb.backup_status_dir' => $this->statusDir.'/missing']);

    expect(BackupStatus::read())->toBe([
        'db' => ['configured' => false, 'late' => false, 'last_success' => null, 'last_attempt' => null],
        'files' => ['configured' => false, 'late' => false, 'last_success' => null, 'last_attempt' => null],
    ])->and(app(BackupLate::class)->count(owner()))->toBe(0);

    config(['zb.backup_status_dir' => null]);

    expect(BackupStatus::read()['db']['configured'])->toBeFalse();
});

it('backup page requires backup.view', function () {
    $this->actingAs(accountant())->get(route('backups.show'))->assertForbidden();
    $this->actingAs(owner())->get(route('backups.show'))->assertOk();
});

it('spec §7 there is no download route', function () {
    $backupRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_contains($route->uri(), 'backup') || str_contains((string) $route->getName(), 'download'))
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($backupRoutes)->toBe(['GET|HEAD settings/backups']);
});
