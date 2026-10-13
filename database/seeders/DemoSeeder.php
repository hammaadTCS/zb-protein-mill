<?php

namespace Database\Seeders;

use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * T2.10 slice review: realistic demo data from docs/acceptance/at1.json (test data,
 * not the client's), loaded through the real Actions: money accounts, parties,
 * opening balances and the purchases of the first trading day.
 *
 * Local computers only: `php artisan db:seed --class=DemoSeeder` after `db:seed`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder is for local use only.');
        }

        $owner = User::query()->where('email', 'test@example.com')->first()
            ?? throw new RuntimeException('Run php artisan db:seed first (it creates the local owner).');

        if (Party::query()->withoutGlobalScope('company')->where('company_id', $owner->company_id)->exists()) {
            throw new RuntimeException('Demo data is already loaded (parties exist). Start again with migrate:fresh --seed.');
        }

        /** @var array{money_accounts: list<array<string, string>>, parties: list<array<string, string>>, opening_balances: list<array<string, string>>, entries: list<array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents(base_path('docs/acceptance/at1.json')), true, flags: JSON_THROW_ON_ERROR);

        Auth::login($owner); // company scope and audit user, as in a real request

        try {
            $this->load($owner, $data);
        } finally {
            Auth::logout();
        }
    }

    /**
     * @param  array{money_accounts: list<array<string, string>>, parties: list<array<string, string>>, opening_balances: list<array<string, string>>, entries: list<array<string, mixed>>}  $data
     */
    private function load(User $owner, array $data): void
    {
        $accounts = [];
        $parties = [];

        foreach ($data['money_accounts'] as $account) {
            $accounts[$account['key']] = app(CreateMoneyAccount::class)->handle($owner, [
                'name' => $account['name'],
                'type' => $account['type'],
                'bank_name' => $account['bank_name'] ?? null,
            ]);
        }

        foreach ($data['parties'] as $party) {
            $parties[$party['key']] = app(CreateParty::class)->handle($owner, [
                'type' => $party['type'],
                'name' => $party['name'],
                'mobile' => $party['mobile'],
                'area' => $party['area'] ?? null,
            ]);
        }

        foreach ($data['opening_balances'] as $opening) {
            app(SetOpeningBalance::class)->handle($owner, isset($opening['money_account'])
                ? ['money_account_id' => $accounts[$opening['money_account']]->id, 'amount' => $opening['amount']]
                : ['party_id' => $parties[$opening['party']]->id, 'side' => $opening['side'], 'amount' => $opening['amount']]);
        }

        $firstDay = $data['entries'][0]['date'];

        foreach ($data['entries'] as $entry) {
            if ($entry['type'] !== 'purchase' || $entry['date'] !== $firstDay) {
                continue;
            }

            $form = [
                'idempotency_key' => (string) Str::uuid(),
                'date' => $entry['date'],
                'party_id' => $parties[$entry['party']]->id,
                'item_id' => Item::query()->where('name', $entry['item'])->value('id'),
                'kg' => $entry['kg'],
                'rate' => $entry['rate'],
            ];

            if (isset($entry['paid_now'])) {
                $form['paid_now'] = [
                    'amount' => $entry['paid_now']['amount'],
                    'money_account_id' => $accounts[$entry['paid_now']['money_account']]->id,
                ];
            }

            app(CreatePurchase::class)->handle($owner, $form);
        }
    }
}
