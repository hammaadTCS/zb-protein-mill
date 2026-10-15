<?php

use Illuminate\Support\Facades\DB;

/**
 * BR-SEC-12, D-43: the host gives one database user with all rights on its own database only,
 * so the VPS's separate migrator login (T1.11, ADR-0007) cannot be made. The compensating
 * controls are the daily zb:verify books check, the off-site backups and the paper register
 * (ADR-0014). The session settings are also asserted live, because they are what zb:verify
 * re-reads every day (BR-OPS-08, T10.1).
 */
it('BR-SEC-12 one database user, and the connection carries the guarded session settings', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections'))->not->toHaveKey('pgsql_migrator')
        ->and(config('database.connections.mysql.username'))->toBe(env('DB_USERNAME'));

    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::selectOne('select @@transaction_isolation as v')->v)->toBe('READ-COMMITTED')
        ->and(DB::selectOne('select @@sql_mode as v')->v)->toContain('STRICT_TRANS_TABLES')
        ->and(DB::selectOne('select @@session.time_zone as v')->v)->toBe('+00:00')
        ->and(DB::selectOne('select @@default_storage_engine as v')->v)->toBe('InnoDB');
});
