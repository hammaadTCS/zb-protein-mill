<?php

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;

it('NFR-02 inside remember() each setting is read once; outside it every read is fresh', function () {
    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, '"settings"')) {
            $queries++;
        }
    });

    Settings::remember(function () {
        foreach (range(1, 50) as $i) {
            Settings::get('number_format');
        }
    });

    expect($queries)->toBe(1);

    DB::table('settings')->where('key', 'kg_decimals')->update(['value' => '0']);

    expect(Settings::get('kg_decimals'))->toBe(0);
});

it('NFR-02 a set() inside remember() is seen by the next read', function () {
    $owner = owner();

    Settings::remember(function () use ($owner) {
        expect(Settings::get('kg_decimals'))->toBe(3);
        Settings::set('kg_decimals', 1, $owner);
        expect(Settings::get('kg_decimals'))->toBe(1);
    });
});

it('NFR-02 a GET request reads each setting at most once', function () {
    $owner = owner();
    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'from "settings"')) {
            $queries++;
        }
    });

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();

    expect($queries)->toBeLessThanOrEqual(count(SettingKeys::all()));
});
