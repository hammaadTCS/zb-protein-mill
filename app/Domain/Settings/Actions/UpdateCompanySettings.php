<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page 42: saves the changed settings, each audited by Settings::set(). A locked key
 * (CompanySettings::locked()) is refused when its value would change.
 */
final class UpdateCompanySettings
{
    /**
     * @param  array<string, mixed>  $values  key => new value; empty values are left as they are
     */
    public function handle(User $by, array $values): void
    {
        DB::transaction(function () use ($by, $values): void {
            $allowed = CompanySettings::keys();
            $locked = CompanySettings::locked();
            $errors = [];

            foreach ($values as $key => $value) {
                if (! in_array($key, $allowed, true)) {
                    $errors[$key] = __('Unknown setting.');

                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $new = Settings::normalize($key, $value);
                $current = Settings::get($key);
                $unchanged = $current !== null && Settings::normalize($key, $current) === $new;

                if ($unchanged) {
                    continue;
                }

                if (isset($locked[$key])) {
                    $errors[$key] = $locked[$key];

                    continue;
                }

                Settings::set($key, $new, $by);
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }
}
