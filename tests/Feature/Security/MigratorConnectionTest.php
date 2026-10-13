<?php

it('BR-SEC-12 the migrator connection differs from pgsql only in username and password', function () {
    $web = config('database.connections.pgsql');
    $migrator = config('database.connections.pgsql_migrator');

    expect($migrator)->toBeArray()
        ->and(array_keys($migrator))->toBe(array_keys($web))
        ->and(array_diff_key($migrator, ['username' => 0, 'password' => 0]))
        ->toBe(array_diff_key($web, ['username' => 0, 'password' => 0]))
        ->and(config('database.default'))->toBe('pgsql');
});
