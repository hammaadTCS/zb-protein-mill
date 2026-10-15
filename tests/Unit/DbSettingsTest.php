<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Card T10.1: the connection settings are enforced in code (never trusted from the
 * server), so the laptop, cloud sessions, CI and the host answer the same. This test
 * needs a live MariaDB but no schema, so it does not use RefreshDatabase: the migrations
 * are ported in T10.2, and this file is the T10.1 evidence that the connection is right.
 */
uses(TestCase::class);

it('T10.1 runs every session with the settings the app enforces', function () {
    $connection = DB::connection();

    expect($connection->getDriverName())->toBe('mysql');

    $isolation = strtoupper(str_replace('-', ' ', (string) $connection->selectOne('select @@session.transaction_isolation as v')->v));
    $modes = array_map('trim', explode(',', (string) $connection->selectOne('select @@session.sql_mode as v')->v));

    expect($isolation)->toBe('READ COMMITTED')
        ->and($modes)->toContain('STRICT_TRANS_TABLES')
        ->and($modes)->toContain('ONLY_FULL_GROUP_BY')
        ->and((string) $connection->selectOne('select @@session.time_zone as v')->v)->toBe('+00:00')
        ->and((string) $connection->selectOne('select @@default_storage_engine as v')->v)->toBe('InnoDB')
        ->and((string) $connection->selectOne('select @@session.collation_connection as v')->v)->toBe('utf8mb4_unicode_ci');
});
