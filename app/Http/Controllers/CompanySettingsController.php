<?php

namespace App\Http\Controllers;

use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Settings\Actions\UpdateCompanySettings;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 42, Company settings (spec §9.6, §11.10): every limit and format the Owner sets. */
class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('settings.manage');

        $locked = CompanySettings::locked();
        $groups = [];

        foreach (CompanySettings::GROUPS as $title => $keys) {
            $fields = [];

            foreach ($keys as $key => $open) {
                $definition = SettingKeys::definition($key);
                $fields[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'choices' => $definition['choices'] ?? null,
                    'min' => $definition['min'] ?? null,
                    'max' => $definition['max'] ?? null,
                    'required' => $definition['required'],
                    'value' => Settings::get($key),
                    'open' => $open,
                    'locked' => $locked[$key] ?? null,
                ];
            }

            $groups[] = ['title' => $title, 'fields' => $fields];
        }

        return Inertia::render('company-settings/index', [
            'groups' => $groups,
            'checklist' => [
                'missing' => array_map(fn (string $key): string => SettingKeys::definition($key)['label'], Settings::missingRequired()),
                'opening_balances_completed' => OpeningBalances::completed(),
                'has_cash_account' => MoneyAccount::query()->active()->where('type', MoneyAccountType::Cash->value)->exists(),
            ],
            'owner_has_two_factor' => $request->user()?->two_factor_confirmed_at !== null,
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, UpdateCompanySettings $action): RedirectResponse
    {
        Gate::authorize('settings.manage');

        /** @var array<string, mixed> $values */
        $values = $request->validated('values');
        $action->handle($this->actor($request), $values);
        Flash::success(__('Settings saved.'));

        return to_route('company-settings.edit');
    }
}
