<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\SystemCompany;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

it('BR-USR-07 zb:create-owner creates the first owner', function () {
    $this->artisan('zb:create-owner')
        ->expectsQuestion('Name', 'Test Owner')
        ->expectsQuestion('E-mail', 'Owner@Example.test')
        ->expectsQuestion('Password', 'first-owner-pass')
        ->expectsQuestion('Password again', 'first-owner-pass')
        ->expectsOutput('Owner Test Owner created.')
        ->assertSuccessful();

    $owner = User::query()->where('email', 'owner@example.test')->sole();

    expect($owner->hasRole(RolesAndPermissionsSeeder::OWNER))->toBeTrue()
        ->and($owner->must_change_password)->toBeFalse()
        ->and($owner->company_id)->toBe(Company::query()->value('id'));
});

it('BR-USR-07 zb:create-owner is audited as server_command', function () {
    $this->artisan('zb:create-owner')
        ->expectsQuestion('Name', 'Test Owner')
        ->expectsQuestion('E-mail', 'owner@example.test')
        ->expectsQuestion('Password', 'first-owner-pass')
        ->expectsQuestion('Password again', 'first-owner-pass')
        ->assertSuccessful();

    $log = AuditLog::query()->where('action', 'server_command')->sole();
    $owner = User::query()->where('email', 'owner@example.test')->sole();

    expect($log->user_id)->toBeNull()
        ->and($log->new_values)->toBe(['command' => 'zb:create-owner', 'user_id' => $owner->id]);
});

it('BR-USR-07 zb:create-owner refuses when an owner exists', function () {
    owner();

    $this->artisan('zb:create-owner')
        ->expectsOutput('An Owner already exists. Add more users on the Users page.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('BR-USR-07 zb:create-owner refuses when system data is missing', function () {
    $this->mock(SystemCompany::class, fn ($mock) => $mock->shouldReceive('find')->andReturnNull());

    $this->artisan('zb:create-owner')
        ->expectsOutput('System data is missing. Deploy a release first (zb-deploy-release runs SystemDataSeeder).')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('BR-USR-09 zb:reset-password prints a temporary password once and forces a change', function () {
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('zb:reset-password', ['email' => $user->email])
        ->expectsOutputToContain("Temporary password for {$user->name}: ")
        ->assertSuccessful();

    $user->refresh();

    expect($user->must_change_password)->toBeTrue()
        ->and(password_verify('password', $user->password))->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'server_command')->sole()->new_values)->toBe(['command' => 'zb:reset-password', 'user_id' => $user->id]);
});

it('BR-USR-09 zb:reset-two-factor clears two-factor', function () {
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::OWNER);
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_recovery_codes' => encrypt('[]'), 'two_factor_confirmed_at' => now()])->save();

    $this->artisan('zb:reset-two-factor', ['email' => $user->email])->assertSuccessful();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'server_command')->sole()->new_values)->toBe(['command' => 'zb:reset-two-factor', 'user_id' => $user->id]);
});

it('BR-USR-09 an unknown e-mail fails with a message', function () {
    $this->artisan('zb:reset-password', ['email' => 'nobody@example.test'])
        ->expectsOutput('No user has this e-mail address.')
        ->assertFailed();

    $this->artisan('zb:reset-two-factor', ['email' => 'nobody@example.test'])
        ->expectsOutput('No user has this e-mail address.')
        ->assertFailed();
});
