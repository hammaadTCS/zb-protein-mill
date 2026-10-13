<?php

use App\Domain\Companies\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SystemDataSeeder;

it('BR-OPS-11 SystemDataSeeder can run twice without creating a second company', function () {
    $this->seed(SystemDataSeeder::class);
    $this->seed(SystemDataSeeder::class);

    expect(Company::query()->count())->toBe(1)
        ->and(Company::query()->value('name'))->toBe('ZB Poultry Protein Mill');
});

it('BR-OPS-11 DatabaseSeeder refuses to run in production', function () {
    $environment = app()['env'];
    app()['env'] = 'production';

    try {
        // Run the class directly: `db:seed` would first ask "are you sure?" in production.
        expect(fn () => app(DatabaseSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'DatabaseSeeder is for local use. Servers use SystemDataSeeder.');
    } finally {
        app()['env'] = $environment;
    }

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('local DatabaseSeeder creates test@example.com in the seeded company', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $users = User::query()->where('email', 'test@example.com')->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->name)->toBe('Test User')
        ->and($users->first()->company_id)->toBe(Company::query()->orderBy('id')->value('id'));
});
