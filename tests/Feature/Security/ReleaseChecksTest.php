<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Inertia\DevTools\DevTools;

/**
 * T8.1 security checklist: the settings the rules depend on, read from the shipped
 * configuration and the production environment template (docs/release-checks.md).
 */
function productionEnv(): array
{
    $values = [];

    foreach (file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m) === 1) {
            $values[$m[1]] = $m[2];
        }
    }

    return $values;
}

it('BR-SEC-02 passwords are stored with bcrypt', function () {
    expect(Hash::info(Hash::make('Long-enough-1!'))['algoName'])->toBe('bcrypt')
        ->and(Hash::isHashed(User::factory()->create()->getAuthPassword()))->toBeTrue();
});

it('BR-SEC-04 a session ends after 60 minutes idle, also without the variable', function () {
    // The shipped values; a developer's own .env may differ.
    expect(productionEnv()['SESSION_LIFETIME'])->toBe('60')
        ->and((string) file_get_contents(base_path('.env.example')))->toContain("SESSION_LIFETIME=60\n")
        ->and((string) file_get_contents(config_path('session.php')))->toContain("env('SESSION_LIFETIME', 60)");
});

it('BR-SEC-07 every web route goes through the CSRF check', function () {
    $web = app(Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];

    expect(collect($web)->contains(fn (string $m): bool => is_a($m, PreventRequestForgery::class, true)))->toBeTrue();
});

it('BR-SEC-15 production cookies are Secure, HttpOnly, SameSite=Lax and sessions are encrypted', function () {
    $env = productionEnv();

    expect([$env['SESSION_SECURE_COOKIE'], $env['SESSION_HTTP_ONLY'], $env['SESSION_SAME_SITE'], $env['SESSION_ENCRYPT'], $env['SESSION_DRIVER']])
        ->toBe(['true', 'true', 'lax', 'true', 'database'])
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

it('BR-SEC-16 production runs without debug output and models are strict elsewhere', function () {
    $env = productionEnv();

    expect([$env['APP_ENV'], $env['APP_DEBUG']])->toBe(['production', 'false'])
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
});

it('BR-SEC-17 production logs rotate daily and are kept 14 days', function () {
    $env = productionEnv();

    expect([$env['LOG_STACK'], $env['LOG_DAILY_DAYS']])->toBe(['daily', '14'])
        ->and(config('logging.channels.daily.driver'))->toBe('daily');
});

it('BR-SEC-14 production sends the Content Security Policy', function () {
    expect(productionEnv()['CSP_ENABLED'])->toBe('true');
});

it('the Inertia devtools are never on in production', function () {
    config(['inertia.devtools.enabled' => null]);
    app()['env'] = 'production';

    expect(DevTools::enabled())->toBeFalse()
        ->and(productionEnv())->not->toHaveKey('INERTIA_DEVTOOLS_ENABLED');

    app()['env'] = 'testing';
});
