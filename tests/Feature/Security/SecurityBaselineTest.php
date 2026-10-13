<?php

use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The nonce of every executable <script> tag of the page. Inertia's page data is a
 * `type="application/json"` block: browsers never run it and CSP does not apply to it
 * (docs/stack-baseline.md, "Root view"), so it is left out.
 */
function scriptNonces(string $html): array
{
    preg_match_all('/<script\b[^>]*>/i', $html, $tags);
    $executable = array_filter($tags[0], fn (string $tag): bool => ! str_contains($tag, 'type="application/json"'));

    return array_values(array_map(function (string $tag): ?string {
        return preg_match('/\bnonce="([^"]*)"/', $tag, $m) === 1 ? $m[1] : null;
    }, $executable));
}

it('BR-SEC-14 an HTML page has a CSP header with a nonce, and the inline script carries the same nonce', function () {
    config(['security.csp_enabled' => true]);

    $response = $this->get('/login')->assertOk();
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect(preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $m))->toBe(1);
    $nonce = $m[1];

    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->and($response->getContent())->toContain('<script nonce="'.$nonce.'">');

    $nonces = scriptNonces($response->getContent());
    expect($nonces)->not->toBeEmpty()
        ->and(array_unique($nonces))->toBe([$nonce])
        // The only script tag without the nonce is Inertia's JSON page data.
        ->and(substr_count($response->getContent(), '<script'))->toBe(count($nonces) + 1)
        ->and($response->getContent())->toContain('<script data-page="app" type="application/json">');
});

it('BR-SEC-14 every request gets a new nonce', function () {
    config(['security.csp_enabled' => true]);

    $first = $this->get('/login')->headers->get('Content-Security-Policy');
    $second = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

it('BR-SEC-14 no CSP header when csp_enabled is false', function () {
    config(['security.csp_enabled' => false]);

    expect($this->get('/login')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('BR-SEC-13 a disabled user is logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')
        ->assertRedirect('/login')
        ->assertSessionHas('status', 'This account is disabled. Ask the owner.');
    $this->assertGuest();
});

it('BR-SEC-13 changing my password ends my other sessions but keeps this one', function () {
    $user = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    // The request must run as a known session: send its id in the session cookie.
    $current = Str::random(40);
    $this->actingAs($user)->withCookie(config('session.cookie'), $current);

    foreach (['other-phone-session', $current] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }
    DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()]);

    $this->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'New-password-2026!',
        'password_confirmation' => 'New-password-2026!',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->orderBy('id')->pluck('id')->all())->toBe([$current, 'someone-else'])
        ->and($user->fresh()->remember_token)->not->toBe($oldRememberToken);
});

it('BR-USR-08 a user with must_change_password can only reach the password page', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $this->actingAs($user);

    $this->get('/dashboard')->assertRedirect('/settings/security');
    $this->get('/settings/profile')->assertRedirect('/settings/security');
    $this->withSession(['auth.password_confirmed_at' => time()])->get('/settings/security')->assertOk();
    $this->get('/user/confirm-password')->assertOk();
});

it('BR-USR-08 saving a new password clears the flag', function () {
    freezeToday('2026-01-31');
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'New-password-2026!',
        'password_confirmation' => 'New-password-2026!',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and($user->password_changed_at?->toDateString())->toBe(now()->toDateString());
    $this->get('/dashboard')->assertOk();
});

it('BR-SEC-09 an owner without two-factor is sent to security settings when owner_two_factor_required is true', function () {
    Settings::set('owner_two_factor_required', true, $owner = owner());

    $this->actingAs($owner)->get('/dashboard')->assertRedirect('/settings/security');

    // The owner can still turn two-step login on from the security page.
    $this->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication');
    expect($owner->fresh()->two_factor_secret)->not->toBeNull();
});

it('BR-SEC-09 an owner without two-factor is not forced when owner_two_factor_required is false', function () {
    $this->actingAs(owner())->get('/dashboard')->assertOk();
});

it('BR-SEC-09 an owner with two-factor passes when it is required', function () {
    Settings::set('owner_two_factor_required', true, User::factory()->create());
    $owner = User::factory()->withTwoFactor()->create()->assignRole('owner');

    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

it('BR-SEC-09 an accountant is never forced', function () {
    Settings::set('owner_two_factor_required', true, User::factory()->create());

    $this->actingAs(accountant())->get('/dashboard')->assertOk();
});

it('BR-OPS-06 /up returns 500 when the database query fails', function () {
    $this->get('/up')->assertOk();

    config(['database.connections.pgsql_down' => [...config('database.connections.pgsql'), 'port' => '1']]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_down');

    try {
        $this->get('/up')->assertStatus(500);
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('pgsql_down');
    }
});

it('BR-SEC-06 the local disk is not served publicly', function () {
    $this->get('/storage/x')->assertNotFound();
});

it('BR-SEC-17 a failed login writes nothing to the log', function () {
    Log::spy();

    $this->post('/login', ['email' => 'someone@example.com', 'password' => 'wrong-password']);
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log', 'write'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
});

it('BR-SEC-16 the 500 page shows no exception text when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('secret-detail-xyz'));

    $response = $this->get('/_test/boom')->assertStatus(500);

    expect($response->getContent())->not->toContain('secret-detail-xyz')
        ->not->toContain('RuntimeException')
        ->not->toContain('<script')
        ->toContain('Something went wrong');
});

it('robots.txt disallows everything', function () {
    expect(file_get_contents(public_path('robots.txt')))->toBe("User-agent: *\nDisallow: /\n");
});

it('the staging banner name is shared with every page', function () {
    config(['app.env_name' => 'staging']);

    $this->actingAs(User::factory()->create())->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('app.env_name', 'staging'));
});
