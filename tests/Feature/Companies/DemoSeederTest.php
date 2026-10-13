<?php

use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

beforeEach(fn () => freezeToday('2026-01-31'));

it('T2.10 DemoSeeder loads the AT-1 parties and first-day purchases through the Actions', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Party::query()->count())->toBe(count(acceptance('at1')['parties']))
        ->and(Purchase::query()->orderBy('id')->pluck('voucher_no')->all())->toBe(['PUR-000001', 'PUR-000002'])
        ->and(auth()->check())->toBeFalse();
});

it('T2.10 DemoSeeder refuses production and a second run', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(fn () => app(DemoSeeder::class)->run())->toThrow(RuntimeException::class, 'Demo data is already loaded');

    app()['env'] = 'production';

    try {
        expect(fn () => app(DemoSeeder::class)->run())->toThrow(RuntimeException::class, 'DemoSeeder is for local use only.');
    } finally {
        app()['env'] = 'testing';
    }
});
