<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Actions\DisableUser;
use App\Domain\Users\Actions\UpdateUserRole;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function giveSessions(User $user, int $count = 2): void
{
    foreach (range(1, $count) as $i) {
        DB::table('sessions')->insert(['id' => "s{$user->id}-{$i}", 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }
}

it('page 43 lists users with role, status, two-step login and last login; accountant gets 403', function () {
    $owner = owner();
    $accountant = accountant();

    $this->actingAs($owner)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users', 2)
            ->where('users', fn ($users) => collect($users)->firstWhere('id', $accountant->id)['role'] === 'accountant'));

    $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();
    $this->actingAs($accountant)->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.test', 'role' => 'owner', 'password' => 'a-long-password'])->assertForbidden();
});

it('BR-USR-08 the owner adds a user with a temporary password', function () {
    $owner = owner();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Test Accountant', 'email' => 'accountant@example.test', 'role' => 'accountant', 'password' => 'temporary-pass-1',
    ])->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'accountant@example.test')->sole();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->hasRole(RolesAndPermissionsSeeder::ACCOUNTANT))->toBeTrue()
        ->and($user->company_id)->toBe($owner->company_id)
        ->and(AuditLog::query()->where('action', 'user_created')->sole()->new_values)
        ->toEqual(['name' => 'Test Accountant', 'email' => 'accountant@example.test', 'role' => 'accountant']);
});

it('BR-USR-04 last owner cannot be disabled or demoted', function () {
    $owner = owner();

    expect(fn () => app(DisableUser::class)->handle($owner, $owner))->toThrow(ValidationException::class, 'At least one active owner is required.')
        ->and(fn () => app(UpdateUserRole::class)->handle($owner, $owner, RolesAndPermissionsSeeder::ACCOUNTANT))
        ->toThrow(ValidationException::class, 'At least one active owner is required.');

    $this->actingAs($owner)->post(route('users.disable', $owner))->assertSessionHasErrors('user');

    // With a second active owner, the first may be demoted.
    $second = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::OWNER);
    app(UpdateUserRole::class)->handle($second, $owner, RolesAndPermissionsSeeder::ACCOUNTANT);

    expect($owner->refresh()->hasRole(RolesAndPermissionsSeeder::ACCOUNTANT))->toBeTrue()
        ->and(fn () => app(DisableUser::class)->handle($second, $second))->toThrow(ValidationException::class);
});

it('BR-USR-03 no delete route', function () {
    $userRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_contains($route->uri(), 'users'));

    expect($userRoutes)->not->toBeEmpty()
        ->and($userRoutes->filter(fn (RoutingRoute $route) => in_array('DELETE', $route->methods(), true)))->toBeEmpty();
});

it('BR-SEC-02 in production a password without a symbol is refused', function () {
    $data = ['name' => 'Test Accountant', 'email' => 'accountant@example.test', 'role' => 'accountant', 'password' => 'NoSymbolHere123'];
    $rules = fn () => (new StoreUserRequest)->rules();

    expect(Validator::make($data, $rules())->fails())->toBeFalse(); // local: the kit's 8-character minimum

    app()['env'] = 'production'; // Password::defaults() is read when the rules are built

    $validator = Validator::make($data, $rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('password'))->toBe('The password field must contain at least one symbol.')
        ->and(Validator::make([...$data, 'password' => 'NoSymbolHere'], (new ResetUserPasswordRequest)->rules())->fails())->toBeTrue();
});

it('disabled user\'s login is refused', function () {
    $owner = owner();
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);

    $this->actingAs($owner)->post(route('users.disable', $user))->assertRedirect(route('users.index'));
    auth()->logout();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account is disabled. Ask the owner.']);
    $this->assertGuest();
});

it('T-17 BR-SEC-13 disabling a user deletes their sessions', function () {
    $owner = owner();
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
    giveSessions($user);

    app(DisableUser::class)->handle($owner, $user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'sessions_ended')->where('auditable_id', $user->id)->sole()->new_values)->toBe(['count' => 2])
        ->and(AuditLog::query()->where('action', 'user_changed')->sole()->new_values)->toBe(['is_active' => false]);
});

it('T-17 BR-SEC-13 changing a role deletes their sessions', function () {
    $owner = owner();
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
    giveSessions($user);

    $this->actingAs($owner)->put(route('users.role', $user), ['role' => 'owner'])->assertRedirect(route('users.index'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->hasRole(RolesAndPermissionsSeeder::OWNER))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user_changed')->sole()->new_values)->toBe(['role' => 'owner']);
});

it('T-17 BR-USR-08 resetting a password deletes their sessions and sets must_change_password', function () {
    $owner = owner();
    $user = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
    giveSessions($user);

    $this->actingAs($owner)->put(route('users.password', $user), ['password' => 'new-temporary-1'])->assertRedirect(route('users.index'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->must_change_password)->toBeTrue()
        ->and(password_verify('new-temporary-1', $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user_changed')->sole()->new_values)->toBe(['password' => 'reset']);
});

it('a disabled user can be enabled again', function () {
    $owner = owner();
    $user = User::factory()->create(['is_active' => false])->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);

    $this->actingAs($owner)->post(route('users.enable', $user))->assertRedirect(route('users.index'));

    expect($user->refresh()->is_active)->toBeTrue();
});
