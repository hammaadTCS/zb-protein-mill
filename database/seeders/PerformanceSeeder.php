<?php

namespace Database\Seeders;

use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * T8.2 (NFR-02): 100,000 invented entries over the last 12 months — 60% purchases,
 * 20% sales, 15% payments, 5% expenses — saved one by one through the real Actions,
 * so the volume matches real use. Local computers only, into an empty database made
 * with `php artisan migrate:fresh --seed`. It takes about an hour.
 *
 *   php artisan db:seed --class=PerformanceSeeder
 */
class PerformanceSeeder extends Seeder
{
    private const ENTRIES = 100_000;

    /** Every warning is confirmed, as a user who knows the data would. */
    private const CONFIRMED = ['duplicate_entry', 'unusual_rate', 'negative_cash', 'missing_reference', 'credit_limit'];

    /** @var list<Party> */
    private array $suppliers = [];

    /** @var list<Party> */
    private array $buyers = [];

    /** @var array<string, MoneyAccount> */
    private array $accounts = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('PerformanceSeeder is for local use only.');
        }

        $owner = User::query()->where('email', 'test@example.com')->first()
            ?? throw new RuntimeException('Run php artisan migrate:fresh --seed first (it creates the local owner).');

        if (Party::query()->withoutGlobalScope('company')->where('company_id', $owner->company_id)->exists()) {
            throw new RuntimeException('This database already has parties. Use an empty one: php artisan migrate:fresh --seed.');
        }

        mt_srand(20260101); // the same data on every run
        Auth::login($owner);

        try {
            $start = CarbonImmutable::parse(Today::date())->subMonthsNoOverflow(12)->startOfMonth();
            Settings::set(SettingKeys::SYSTEM_START_DATE, $start->toDateString(), $owner);
            $this->masterData($owner);
            $this->entries($owner, $start);
        } finally {
            Auth::logout();
        }
    }

    private function masterData(User $owner): void
    {
        foreach ([['cash', 'Cash', 'cash', null], ['hbl', 'HBL', 'bank', 'HBL'], ['mcb', 'MCB', 'bank', 'MCB']] as [$key, $name, $type, $bank]) {
            $this->accounts[$key] = app(CreateMoneyAccount::class)->handle($owner, array_filter(['name' => $name, 'type' => $type, 'bank_name' => $bank]));
            app(SetOpeningBalance::class)->handle($owner, ['money_account_id' => $this->accounts[$key]->id, 'amount' => '50000000']);
        }

        for ($i = 1; $i <= 60; $i++) {
            $this->suppliers[] = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => "Supplier {$i}", 'mobile' => sprintf('0300%07d', $i)]);
        }

        for ($i = 1; $i <= 30; $i++) {
            $this->buyers[] = app(CreateParty::class)->handle($owner, ['type' => 'buyer', 'name' => "Buyer {$i}", 'mobile' => sprintf('0301%07d', $i), 'payment_terms_days' => 15]);
        }
    }

    private function entries(User $owner, CarbonImmutable $start): void
    {
        $days = (int) $start->diffInDays(CarbonImmutable::parse(Today::date())) + 1;
        $items = Item::query()->pluck('id', 'code')->all();
        $categories = array_values(array_map('intval', ExpenseCategory::query()->pluck('id')->all()));

        for ($i = 0; $i < self::ENTRIES; $i++) {
            $date = $start->addDays(intdiv($i * $days, self::ENTRIES))->toDateString();
            $slot = $i % 20;

            match (true) {
                $slot < 12 => $this->purchase($owner, $date, $items),
                $slot < 16 => $this->sale($owner, $date, $items),
                $slot < 19 => $this->payment($owner, $date),
                default => $this->expense($owner, $date, $categories),
            };

            if (($i + 1) % 1000 === 0) {
                $this->command->info(($i + 1).' entries');
            }
        }
    }

    /**
     * @param  array<string, int>  $items
     */
    private function purchase(User $owner, string $date, array $items): void
    {
        $wood = mt_rand(1, 10) === 1;
        $kg = (string) mt_rand(100, 2000);
        $rate = mt_rand(1, 50) === 1 ? null : ($wood ? (string) mt_rand(8, 12) : (string) mt_rand(40, 60));
        $form = [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $date,
            'party_id' => $this->suppliers[array_rand($this->suppliers)]->id,
            'item_id' => $items[$wood ? 'WOOD' : 'WASTE'],
            'kg' => $kg,
            'rate' => $rate,
            'slip_no' => (string) mt_rand(1000, 99999),
            'our_weight_kg' => mt_rand(1, 3) === 1 ? (string) ((int) $kg - mt_rand(0, 20)) : null,
        ];

        if ($rate !== null && mt_rand(1, 10) <= 3) {
            $form['paid_now'] = $this->money(Decimal::amount($kg, $rate));
        }

        app(CreatePurchase::class)->handle($owner, $form, self::CONFIRMED);
    }

    /**
     * @param  array<string, int>  $items
     */
    private function sale(User $owner, string $date, array $items): void
    {
        $oil = mt_rand(1, 10) <= 3;
        $kg = (string) mt_rand(50, 500);
        $rate = $oil ? (string) mt_rand(250, 320) : (string) mt_rand(180, 240);
        $form = [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $date,
            'party_id' => $this->buyers[array_rand($this->buyers)]->id,
            'item_id' => $items[$oil ? 'OILFAT' : 'PROTEIN'],
            'kg' => $kg,
            'rate' => $rate,
        ];

        if (mt_rand(1, 10) <= 3) {
            $form['received_now'] = $this->money(Decimal::amount($kg, $rate));
        }

        app(CreateSale::class)->handle($owner, $form, self::CONFIRMED);
    }

    private function payment(User $owner, string $date): void
    {
        $paid = mt_rand(1, 3) <= 2;
        $parties = $paid ? $this->suppliers : $this->buyers;

        app(CreatePayment::class)->handle($owner, [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $date,
            'direction' => $paid ? 'paid' : 'received',
            'party_id' => $parties[array_rand($parties)]->id,
            ...$this->money((string) mt_rand(5000, 100000)),
        ], self::CONFIRMED);
    }

    /**
     * @param  list<int>  $categories
     */
    private function expense(User $owner, string $date, array $categories): void
    {
        app(CreateExpense::class)->handle($owner, [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $date,
            'category_id' => $categories[array_rand($categories)],
            'amount' => (string) mt_rand(100, 20000),
            'payment_mode' => 'paid',
            'money_account_id' => $this->accounts['cash']->id,
            'note' => 'Performance test',
        ], self::CONFIRMED);
    }

    /**
     * @return array{amount: string, money_account_id: int, reference_no: string|null}
     */
    private function money(string $amount): array
    {
        $key = ['cash', 'hbl', 'mcb'][mt_rand(0, 2)];

        return ['amount' => $amount, 'money_account_id' => $this->accounts[$key]->id, 'reference_no' => $key === 'cash' ? null : 'PERF-'.mt_rand(100000, 999999)];
    }
}
