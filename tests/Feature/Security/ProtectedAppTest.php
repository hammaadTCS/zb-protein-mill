<?php

use App\Domain\Parties\Actions\CreateParty;
use App\Support\ConcurrentEdit;
use App\Support\Warnings\WarningRequired;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Inertia\DevTools\DevTools;
use Symfony\Component\Finder\Finder;

it('BR-SEC-17 a duplicate-party warning is answered but never reported to the log', function () {
    Exceptions::fake();
    $owner = owner();
    app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);

    $this->actingAs($owner)
        ->post(route('parties.store'), ['type' => 'buyer', 'name' => 'Aslam Feeds', 'mobile' => '0300 0000001'])
        ->assertStatus(422)
        ->assertJsonPath('warning.code', 'duplicate_party');

    Exceptions::assertNotReported(WarningRequired::class);
});

it('BR-SEC-17 a concurrent edit is answered but never reported to the log', function () {
    Exceptions::fake();
    Route::middleware('web')->post('/_test/concurrent', fn () => throw new ConcurrentEdit);

    $this->actingAs(owner())->postJson('/_test/concurrent')
        ->assertStatus(409)
        ->assertJsonPath('message', 'This entry was changed by another user. Reload.');

    Exceptions::assertNotReported(ConcurrentEdit::class);
});

it('BR-SEC-17 the Inertia DevTools recorder is off unless turned on locally', function () {
    expect(DevTools::enabled())->toBeFalse();

    $this->actingAs(owner())->get('/_inertia/devtools/entries')->assertNotFound();
});

it('BR-SEC-17 the DevTools switch works only with APP_ENV=local', function () {
    $devtoolsWith = function (string $appEnv, ?string $switch): bool {
        $saved = [$_ENV, $_SERVER];
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $appEnv;

        if ($switch === null) {
            unset($_ENV['INERTIA_DEVTOOLS_ENABLED'], $_SERVER['INERTIA_DEVTOOLS_ENABLED']);
        } else {
            $_ENV['INERTIA_DEVTOOLS_ENABLED'] = $_SERVER['INERTIA_DEVTOOLS_ENABLED'] = $switch;
        }

        try {
            return (require config_path('inertia.php'))['devtools']['enabled'];
        } finally {
            [$_ENV, $_SERVER] = $saved;
        }
    };

    expect($devtoolsWith('production', 'true'))->toBeFalse()
        ->and($devtoolsWith('staging', 'true'))->toBeFalse()
        ->and($devtoolsWith('local', null))->toBeFalse()
        ->and($devtoolsWith('local', 'true'))->toBeTrue();
});

it('BR-SEC-14 no screen links to another site', function () {
    $files = Finder::create()->files()
        ->in([resource_path('js'), resource_path('views')])
        ->exclude(['actions', 'routes', 'wayfinder'])
        ->name(['*.ts', '*.tsx', '*.php']);

    $found = [];

    foreach ($files as $file) {
        foreach (preg_split('/\R/', $file->getContents()) ?: [] as $number => $line) {
            $code = ltrim($line);

            // Comments may credit a source; only code can link or load.
            if (str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*')) {
                continue;
            }

            if (preg_match('#https?://(?!localhost\b|127\.0\.0\.1\b)#', $line) === 1) {
                $found[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($found)->toBe([]);
});
