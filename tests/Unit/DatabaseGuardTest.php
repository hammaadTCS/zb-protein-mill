<?php

use App\Support\Database\DatabaseGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Card T10.1: the app refuses to run on a database that is not the MariaDB of record
 * running with the enforced session settings, and the message names every failing item.
 * This test needs a live MariaDB but no schema, so it does not use RefreshDatabase (the
 * migrations arrive in T10.2). It plants its bad data in the test only.
 */
uses(TestCase::class);

it('T10.1 refuses a session sql_mode without strict mode', function () {
    config()->set('database.connections.mysql.modes', ['NO_ENGINE_SUBSTITUTION']);
    DB::purge('mysql');

    expect(DatabaseGuard::failures())
        ->toContain('the session sql_mode does not include STRICT_TRANS_TABLES')
        ->toContain('the session sql_mode does not include ONLY_FULL_GROUP_BY');
});

it('T10.1 refuses a connection time zone that is not +00:00', function () {
    config()->set('database.connections.mysql.timezone', '+05:00');
    DB::purge('mysql');

    expect(DatabaseGuard::failures())->toContain('the connection time zone is not +00:00');
});

it('T10.1 refuses a table that is not InnoDB', function () {
    DB::statement('create table if not exists zb_guard_probe (id int primary key) engine = MyISAM');

    try {
        expect(DatabaseGuard::failures())->toContain('table zb_guard_probe is not InnoDB');
    } finally {
        DB::statement('drop table if exists zb_guard_probe');
    }
});

it('T10.1 names a protection trigger that is missing', function () {
    config()->set('zb.required_triggers', ['no_such_trigger']);

    expect(DatabaseGuard::failures())->toContain('the protection trigger no_such_trigger is missing');
});
