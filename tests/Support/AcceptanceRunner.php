<?php

namespace Tests\Support;

use App\Domain\Adjustments\Actions\CreateAdjustment;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Insights\BusinessInsights;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Actions\MarkOpeningBalancesComplete;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\MergeParties;
use App\Domain\Parties\Models\Party;
use App\Domain\Partners\Actions\CreatePartnerTransaction;
use App\Domain\Payments\Actions\ApprovePayment;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Actions\CloseMonth;
use App\Domain\Periods\Actions\PostCashDifference;
use App\Domain\Periods\Actions\RecordBankStatement;
use App\Domain\Periods\Month;
use App\Domain\Periods\MonthEndCheck;
use App\Domain\Purchases\Actions\AddPurchaseRate;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\CreateRateCorrection;
use App\Domain\Purchases\Actions\UpdatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Reports\Queries\IncomeVsSpending;
use App\Domain\Reports\Queries\PartnerStatement;
use App\Domain\Reports\Queries\TrialBalance;
use App\Domain\Sales\Actions\AddSaleRate;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Domain\Transfers\Actions\CreateTransfer;
use App\Models\User;
use App\Support\Today;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TestExpenseCategoriesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * docs/acceptance/README.md: runs an acceptance file through the real Action classes
 * (never HTTP, never direct model writes) and checks every expected key. Built in T4.8;
 * the month-end, merge and step-level checks of controls.json came with T7.1 to T7.7.
 */
final class AcceptanceRunner
{
    /**
     * Checks that a later task switches on (README: "skipped only by the task that
     * builds the runner"). Each names the task that removes it from this list.
     */
    private const SKIPPED = [];

    /** Every top-level `expected` key this runner checks; any other key fails the test. */
    private const KNOWN_EXPECTED = '/^(money_accounts|parties|partners|ledger_accounts|trial_balance|income_vs_spending|kg|vouchers|audit|reversal_journals|rate_changes|insights|cash_shortage_journal|merge_journal|rate_correction|rate_correction_journal|period_\d{4}_\d{2}|purchase_\w+_party|purchase_\w+_unchanged)$/';

    public User $owner;

    public User $accountant;

    /** @var array<string, MoneyAccount> */
    public array $accounts = [];

    /** @var array<string, Party> */
    public array $parties = [];

    /** @var array<string, Model> records by `ref` */
    public array $refs = [];

    /** @var array<string, Model> records by entry `no` */
    public array $records = [];

    /**
     * @param  array<string, mixed>  $data  one acceptance file, or one case of controls.json
     * @param  array<string, mixed>|null  $base  the file a case builds on (`base`), run first
     */
    private function __construct(public readonly array $data, private readonly ?array $base = null) {}

    /** Loads `docs/acceptance/{file}.json`, or one case of it by id. */
    public static function load(string $file, ?string $caseId = null): self
    {
        $data = json_decode((string) file_get_contents(base_path("docs/acceptance/{$file}.json")), true, flags: JSON_THROW_ON_ERROR);

        if ($caseId !== null) {
            $data = collect($data['cases'])->firstWhere('id', $caseId) ?? throw new LogicException("No case {$caseId} in {$file}.json.");

            if (isset($data['base'])) {
                return new self($data, json_decode((string) file_get_contents(base_path("docs/acceptance/{$data['base']}")), true, flags: JSON_THROW_ON_ERROR));
            }
        }

        return new self($data);
    }

    /** Steps 1 and 2: today, users, expense categories, accounts, parties, openings marked complete. */
    public function setUp(): self
    {
        $world = $this->base ?? $this->data;
        freezeToday($world['today']);
        $this->owner = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::OWNER);
        $this->accountant = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT);
        app(TestExpenseCategoriesSeeder::class)->run();

        foreach ($world['money_accounts'] ?? [] as $account) {
            $this->accounts[$account['key']] = app(CreateMoneyAccount::class)->handle($this->owner, $account);
        }

        foreach ($world['parties'] ?? [] as $party) {
            $this->parties[$party['key']] = app(CreateParty::class)->handle($this->owner, $party);
        }

        foreach ($world['opening_balances'] ?? [] as $opening) {
            app(SetOpeningBalance::class)->handle($this->owner, isset($opening['money_account'])
                ? ['money_account_id' => $this->accounts[$opening['money_account']]->id, 'amount' => $opening['amount']]
                : ['party_id' => $this->parties[$opening['party']]->id, 'side' => $opening['side'] ?? null, 'amount' => $opening['amount']]);
        }

        app(MarkOpeningBalancesComplete::class)->handle($this->owner, OpeningBalances::equity());

        return $this;
    }

    /** Step 3: every entry (or step) in file order; a case's base entries first. */
    public function runAll(): self
    {
        foreach ([...$this->base['entries'] ?? [], ...$this->data['entries'] ?? $this->data['steps'] ?? []] as $entry) {
            $this->run($entry);
        }

        return $this;
    }

    /**
     * Runs one entry as its user (step 3) and keeps it by `ref` and `no` (step 4).
     * Warnings are never confirmed (step 5): a WarningRequired fails the test.
     *
     * @param  array<string, mixed>  $entry
     */
    public function run(array $entry): ?Model
    {
        $by = ($entry['as'] ?? 'owner') === 'accountant' ? $this->accountant : $this->owner;

        $record = match ($entry['type']) {
            'purchase' => app(CreatePurchase::class)->handle($by, $this->entryForm($entry, 'paid_now')),
            'sale' => app(CreateSale::class)->handle($by, $this->entryForm($entry, 'received_now')),
            'add_rate' => $this->addRate($by, $entry),
            'edit_purchase' => $this->editPurchase($by, $entry),
            'payment' => app(CreatePayment::class)->handle($by, [
                'idempotency_key' => (string) Str::uuid(),
                'date' => $entry['date'],
                'direction' => $entry['direction'],
                'party_id' => $this->parties[$entry['party']]->id,
                'amount' => $entry['amount'],
                'money_account_id' => $this->accounts[$entry['money_account']]->id,
                'reference_no' => $entry['reference_no'] ?? null,
            ]),
            'approve' => $this->approve($by, $entry),
            'expense' => app(CreateExpense::class)->handle($by, $this->expenseForm($entry)),
            'edit_expense' => $this->editExpense($by, $entry),
            'partner' => app(CreatePartnerTransaction::class)->handle($by, [
                'idempotency_key' => (string) Str::uuid(),
                'date' => $entry['date'],
                'party_id' => $this->parties[$entry['party']]->id,
                'type' => $entry['partner_type'],
                'amount' => $entry['amount'],
                'money_account_id' => $this->accounts[$entry['money_account']]->id,
            ]),
            'transfer' => app(CreateTransfer::class)->handle($by, [
                'idempotency_key' => (string) Str::uuid(),
                'date' => $entry['date'],
                'from_account_id' => $this->accounts[$entry['from']]->id,
                'to_account_id' => $this->accounts[$entry['to']]->id,
                'amount' => $entry['amount'],
                'reference_no' => $entry['reference_no'] ?? null,
            ]),
            'adjustment' => app(CreateAdjustment::class)->handle($by, $this->target($entry, Purchase::class, Sale::class), [
                'idempotency_key' => (string) Str::uuid(),
                'date' => $entry['date'],
                'amount' => $entry['amount'],
                'reason' => $entry['reason'],
                'note' => $entry['note'],
            ]),
            'rate_correction' => app(CreateRateCorrection::class)->handle($by, $this->target($entry, Purchase::class, Sale::class), $entry['new_rate'], $entry['date'], $entry['reason']),
            'set_today' => $this->setToday($entry['date']),
            'bank_statement' => app(RecordBankStatement::class)->handle($by, $entry['period'], $this->accounts[$entry['money_account']], $entry['statement_balance']),
            'cash_count' => app(CreateCashCount::class)->handle($by, $this->accounts[$entry['money_account']], $entry['date'], $entry['counted_amount']),
            'cash_shortage' => $this->cashShortage($by, $entry),
            'close_month' => $this->closeMonth($by, $entry),
            'merge' => app(MergeParties::class)->handle($by, $this->parties[$entry['source']], $this->parties[$entry['target']]),
            'attempt' => $this->attempt($entry),
            default => throw new LogicException("Acceptance entry type {$entry['type']} is not built yet."),
        };

        // A step's own `expect` (controls.json AT-4); close_month checks its own.
        if (isset($entry['expect']) && $entry['type'] !== 'close_month') {
            $this->assertStep($record, $entry['expect'], "{$entry['type']} ".($entry['ref'] ?? $entry['target'] ?? ''));
        }

        if ($record instanceof Model) {
            if (isset($entry['ref'])) {
                $this->refs[$entry['ref']] = $record;
            }

            if (isset($entry['no'])) {
                $this->records[(string) $entry['no']] = $record;
            }
        }

        return $record;
    }

    /** Step 6: every key in `expected`. */
    public function assertExpected(): self
    {
        $expected = $this->data['expected'] ?? [];
        $unknown = array_filter(array_keys($expected), fn (string $key): bool => preg_match(self::KNOWN_EXPECTED, $key) !== 1);
        expect(array_values($unknown))->toBe([], 'expected keys this runner does not check');
        $today = Today::date();

        foreach ($expected['money_accounts'] ?? [] as $key => $balance) {
            expect(ledgerBalance($this->accounts[$key]->ledgerAccount->code))->toBe($balance, "money_accounts.{$key}");
        }

        foreach ($expected['parties'] ?? [] as $key => $party) {
            $this->assertParty($key, $party);
        }

        foreach ($expected['partners'] ?? [] as $key => $partner) {
            $row = collect(PartnerStatement::between($this->startDate(), $today, $this->parties[$key]->id))->sole();
            expect(['invested' => $row['invested'], 'withdrawn' => $row['withdrawn'], 'net' => $row['net']])->toBe($partner, "partners.{$key}");
        }

        foreach ($expected['ledger_accounts'] ?? [] as $key => $account) {
            $balance = ledgerBalance($this->ledgerCode((string) $key));
            $side = $balance === '0.00' ? 'zero' : (str_starts_with($balance, '-') ? 'credit' : 'debit');
            expect(['side' => $side, 'amount' => ltrim($balance, '-')])->toBe($account, "ledger_accounts.{$key}");
        }

        if (isset($expected['trial_balance'])) {
            $accountLevel = TrialBalance::asOf($today);
            $withParties = TrialBalance::asOf($today, withParties: true);
            expect(['debit' => $accountLevel->totalDebit, 'credit' => $accountLevel->totalCredit])->toBe($expected['trial_balance']['account_level'], 'trial_balance.account_level')
                ->and(['debit' => $withParties->totalDebit, 'credit' => $withParties->totalCredit])->toBe($expected['trial_balance']['with_parties'], 'trial_balance.with_parties')
                ->and($accountLevel->balanced && $withParties->balanced)->toBeTrue('trial_balance balanced');
        }

        if (isset($expected['income_vs_spending'])) {
            $this->assertIncomeVsSpending($expected['income_vs_spending']);
        }

        foreach ($expected['kg'] ?? [] as $key => $kg) {
            expect($this->kgTotal((string) $key))->toBe($kg, "kg.{$key}");
        }

        foreach ($expected['vouchers'] ?? [] as $key => $voucher) {
            expect($this->voucher((string) $key))->toBe($voucher, "vouchers.{$key}");
        }

        foreach ($expected['audit'] ?? [] as $row) {
            $record = $this->refs[$row['entry']];
            $log = AuditLog::query()->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->getKey())->where('action', $row['action'])->sole();
            expect(['old' => $log->old_values, 'new' => $log->new_values])->toBe(['old' => $row['old'], 'new' => $row['new']], "audit.{$row['entry']}");
        }

        foreach ($expected['reversal_journals'] ?? [] as $ref => $count) {
            $record = $this->refs[$ref];
            $reversals = DB::table('journal_entries')->where('source_type', $record->getMorphClass())->where('source_id', $record->getKey())->whereNotNull('reversal_of_id')->count();
            expect($reversals)->toBe($count, "reversal_journals.{$ref}");
        }

        foreach ($expected['rate_changes'] ?? [] as $i => $row) {
            $record = $this->refs[$row['entry']];
            $change = RateChange::query()->where('target_type', $record->getMorphClass())->where('target_id', $record->getKey())->orderBy('id')->get()->values()->get($i);
            expect([
                'old_rate' => $change?->old_rate, 'new_rate' => $change?->new_rate, 'old_amount' => $change?->old_amount, 'new_amount' => $change?->new_amount,
            ])->toBe(array_diff_key($row, ['entry' => 0]), "rate_changes.{$i}");
        }

        if (isset($expected['cash_shortage_journal'])) {
            $journal = JournalEntry::query()->where('source_type', 'month_end_adjustment')->orderBy('id')->sole();
            expect(['posting_date' => $journal->posting_date, 'voucher' => preg_match('/CSH-\d{6}/', $journal->description, $m) === 1 ? $m[0] : null])
                ->toBe($expected['cash_shortage_journal'], 'cash_shortage_journal');
        }

        foreach ($expected as $key => $value) {
            // `purchase_<ref>_party`: the party a purchase still belongs to.
            if (preg_match('/^purchase_(\w+)_party$/', (string) $key, $m) === 1) {
                expect($this->refs[$m[1]]->refresh()->getAttribute('party_id'))->toBe($this->parties[$value]->id, (string) $key);
            }
        }

        foreach ($expected as $key => $value) {
            // `purchase_<ref>_unchanged`: a closed-month entry keeps its rate and amount (AT-5).
            if (preg_match('/^purchase_(\w+)_unchanged$/', (string) $key, $m) === 1) {
                $purchase = $this->refs[$m[1]]->refresh();
                expect(['rate' => $purchase->getAttribute('rate'), 'amount' => $purchase->getAttribute('amount')])->toBe($value, (string) $key);
            }
        }

        if (isset($expected['rate_correction'])) {
            $correction = RateCorrection::query()->sole();
            expect([
                'voucher' => $correction->voucher_no, 'correction_date' => $correction->correction_date,
                'old_rate' => $correction->old_rate, 'new_rate' => $correction->new_rate,
                'old_amount' => $correction->old_amount, 'new_amount' => $correction->new_amount, 'difference' => $correction->difference,
            ])->toBe($expected['rate_correction'], 'rate_correction');
        }

        if (isset($expected['rate_correction_journal'])) {
            $this->assertJournal(JournalEntry::query()->where('source_type', 'rate_correction')->sole(), $expected['rate_correction_journal'], 'rate_correction_journal');
        }

        if (isset($expected['merge_journal'])) {
            $this->assertJournal(JournalEntry::query()->where('source_type', 'party_merge')->sole(), $expected['merge_journal'], 'merge_journal');
        }

        foreach ($expected as $key => $status) {
            if (preg_match('/^period_(\d{4})_(\d{2})$/', (string) $key, $m) === 1) {
                expect(Month::of("{$m[1]}-{$m[2]}")->isClosed() ? 'closed' : 'open')->toBe($status, (string) $key);
            }
        }

        // R-13 over the default period (`insights_period_months` back from today), from T7.5.
        if (isset($expected['insights'])) {
            $period = BusinessInsights::defaultPeriod();
            expect(array_intersect_key(BusinessInsights::for($period['from'], $period['to']), $expected['insights']))->toBe($expected['insights'], 'insights');
        }

        return $this;
    }

    /** @return array<string, string> the checks this runner does not run yet, and why */
    public static function skipped(): array
    {
        return self::SKIPPED;
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function assertParty(string $key, array $party): void
    {
        $model = $this->parties[$key]->refresh();
        $balance = partyBalance($model);

        expect(['words' => $balance['words'], 'amount' => $balance['amount']])->toBe(['words' => $party['words'], 'amount' => $party['amount']], "parties.{$key}");

        if (isset($party['pending_kg'])) {
            expect($this->pendingKg(fn ($q) => $q->where('party_id', $model->id)))->toBe($party['pending_kg'], "parties.{$key}.pending_kg");
        }

        if (isset($party['status'])) {
            expect($model->status->value)->toBe($party['status'], "parties.{$key}.status");
        }

        if (isset($party['merged_into'])) {
            expect($model->merged_into_id)->toBe($this->parties[$party['merged_into']]->id, "parties.{$key}.merged_into");
        }
    }

    /**
     * R-08 from the report itself (switched on in T6.7).
     *
     * @param  array<string, string>  $expected
     */
    private function assertIncomeVsSpending(array $expected): void
    {
        $report = IncomeVsSpending::for($expected['from'], $expected['to']);

        expect(['income' => $report['income'], 'spending' => $report['spending'], 'result' => $report['result'], 'pending_kg' => $report['pending_kg']])
            ->toBe(array_intersect_key($expected, ['income' => 0, 'spending' => 0, 'result' => 0, 'pending_kg' => 0]), 'income_vs_spending');
    }

    /** Rate-pending kg of active purchases and sales matching the filter, 3 decimals. */
    private function pendingKg(callable $filter): string
    {
        $total = '0';

        foreach ([Purchase::class, Sale::class] as $model) {
            $kg = $filter($model::query()->where('status', 'active')->where('rate_status', 'pending'))
                ->toBase()->selectRaw('CAST(COALESCE(SUM(kg), 0) AS DECIMAL(14,3)) AS kg')->value('kg');
            $total = (string) DB::selectOne('SELECT CAST(CAST(? AS DECIMAL(14,3)) + CAST(? AS DECIMAL(14,3)) AS CHAR) AS t', [$total, $kg])->t;
        }

        return (string) DB::selectOne('SELECT CAST(? AS DECIMAL(14,3)) AS t', [$total])->t;
    }

    /** `<Item name>_bought` or `<Item name>_sold`: kg of active entries. */
    private function kgTotal(string $key): string
    {
        [$item, $side] = [substr($key, 0, (int) strrpos($key, '_')), substr($key, (int) strrpos($key, '_') + 1)];
        $model = $side === 'bought' ? Purchase::class : Sale::class;

        return (string) $model::query()
            ->where('status', 'active')
            ->where('item_id', Item::query()->where('name', $item)->sole()->id)
            ->toBase()
            ->selectRaw('CAST(COALESCE(SUM(kg), 0) AS DECIMAL(14,3)) AS kg')
            ->value('kg');
    }

    /** A voucher by entry `no`, or `<no>.paid_now` / `<no>.received_now` for the linked payment. */
    private function voucher(string $key): ?string
    {
        if (str_contains($key, '.')) {
            [$no] = explode('.', $key);
            $entry = $this->records[$no];

            return Payment::query()->where($entry instanceof Sale ? 'sale_id' : 'purchase_id', $entry->getKey())->orderBy('id')->value('voucher_no');
        }

        $record = $this->records[$key];

        return (string) $record->getAttribute('voucher_no');
    }

    /** `money:<key>`, `category:<name>` or a ledger code. */
    private function ledgerCode(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'money:') => $this->accounts[substr($key, 6)]->ledgerAccount->code,
            str_starts_with($key, 'category:') => ExpenseCategory::query()->where('name', substr($key, 9))->sole()->ledgerAccount->code,
            default => $key,
        };
    }

    private function startDate(): string
    {
        return (string) Settings::get(SettingKeys::SYSTEM_START_DATE);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function entryForm(array $entry, string $moneyKey): array
    {
        $form = [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $entry['date'],
            'party_id' => $this->parties[$entry['party']]->id,
            'item_id' => Item::query()->where('name', $entry['item'])->sole()->id,
            'kg' => $entry['kg'],
            'rate' => $entry['rate'],
        ];

        if (isset($entry[$moneyKey])) {
            $form[$moneyKey] = [
                'amount' => $entry[$moneyKey]['amount'],
                'money_account_id' => $this->accounts[$entry[$moneyKey]['money_account']]->id,
                'reference_no' => $entry[$moneyKey]['reference_no'] ?? null,
            ];
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function expenseForm(array $entry): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'date' => $entry['date'],
            'category_id' => ExpenseCategory::query()->where('name', $entry['category'])->sole()->id,
            'amount' => $entry['amount'],
            'payment_mode' => $entry['payment_mode'],
            'money_account_id' => isset($entry['money_account']) ? $this->accounts[$entry['money_account']]->id : null,
            'party_id' => isset($entry['party']) ? $this->parties[$entry['party']]->id : null,
            'linked_purchase_id' => isset($entry['linked_purchase']) ? $this->refs[$entry['linked_purchase']]->getKey() : null,
            'linked_sale_id' => isset($entry['linked_sale']) ? $this->refs[$entry['linked_sale']]->getKey() : null,
            'note' => $entry['note'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function addRate(User $by, array $entry): Model
    {
        $target = $this->target($entry, Purchase::class, Sale::class);

        return $target instanceof Sale
            ? app(AddSaleRate::class)->handle($by, $target, $entry['rate'], $entry['date'])
            : app(AddPurchaseRate::class)->handle($by, $target, $entry['rate'], $entry['date']);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function editPurchase(User $by, array $entry): Model
    {
        $purchase = $this->target($entry, Purchase::class);

        return app(UpdatePurchase::class)->handle($by, $purchase, [
            'version' => $purchase->updated_at?->toIso8601String(),
            'date' => $purchase->entry_date,
            'party_id' => $purchase->party_id,
            'item_id' => $purchase->item_id,
            'kg' => $purchase->kg,
            'rate' => $purchase->rate,
            'slip_no' => $purchase->slip_no,
            'our_weight_kg' => $purchase->our_weight_kg,
            'note' => $purchase->note,
            ...$entry['changes'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function editExpense(User $by, array $entry): Model
    {
        $expense = $this->target($entry, Expense::class);

        return app(UpdateExpense::class)->handle($by, $expense, [
            'version' => $expense->updated_at?->toIso8601String(),
            'date' => $expense->entry_date,
            'category_id' => $expense->category_id,
            'amount' => $expense->amount,
            'payment_mode' => $expense->payment_mode->value,
            'money_account_id' => $expense->money_account_id,
            'party_id' => $expense->party_id,
            'handled_by_party_id' => $expense->handled_by_party_id,
            'linked_purchase_id' => $expense->linked_purchase_id,
            'linked_sale_id' => $expense->linked_sale_id,
            'note' => $expense->note,
            ...$entry['changes'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function approve(User $by, array $entry): Model
    {
        $target = $this->refs[$entry['target']] ?? throw new LogicException("Unknown ref {$entry['target']}.");
        $payment = $target instanceof Payment ? $target : Payment::query()
            ->where($target instanceof Sale ? 'sale_id' : 'purchase_id', $target->getKey())->orderBy('id')->firstOrFail();

        return app(ApprovePayment::class)->handle($by, $payment->refresh());
    }

    /**
     * `attempt`: the action must be refused with a message containing the given text.
     *
     * @param  array<string, mixed>  $entry
     */
    private function attempt(array $entry): null
    {
        try {
            $this->run($entry['action']);
        } catch (ValidationException $e) {
            expect(implode(' ', $e->validator->errors()->all()))->toContain($entry['expect_error_contains']);

            return null;
        }

        throw new LogicException("The attempt was not refused: {$entry['action']['type']}.");
    }

    /**
     * @template T of Model
     *
     * @param  array<string, mixed>  $entry
     * @param  class-string<T>  ...$types
     * @return T
     */
    private function target(array $entry, string ...$types): Model
    {
        $record = ($this->refs[$entry['target']] ?? throw new LogicException("Unknown ref {$entry['target']}."))->refresh();

        foreach ($types as $type) {
            if ($record instanceof $type) {
                return $record;
            }
        }

        throw new LogicException("Ref {$entry['target']} is not a ".implode(' or ', $types).'.');
    }

    /**
     * A step's `expect`: approval status, whether it has a journal and its posting
     * date, and party and money account balances right after it (AT-4).
     *
     * @param  array<string, mixed>  $expect
     */
    private function assertStep(?Model $record, array $expect, string $label): void
    {
        expect(array_values(array_diff(array_keys($expect), ['approval_status', 'journal', 'journal_posting_date', 'parties', 'money_accounts'])))->toBe([], "{$label}: expect keys this runner does not check");

        $record?->refresh();

        if (isset($expect['approval_status'])) {
            expect($record?->getAttribute('approval_status')?->value)->toBe($expect['approval_status'], "{$label}.approval_status");
        }

        if (isset($expect['journal'])) {
            expect($record?->getAttribute('journal_entry_id') !== null)->toBe($expect['journal'], "{$label}.journal");
        }

        if (isset($expect['journal_posting_date'])) {
            expect(JournalEntry::query()->findOrFail($record?->getAttribute('journal_entry_id'))->posting_date)->toBe($expect['journal_posting_date'], "{$label}.journal_posting_date");
        }

        foreach ($expect['parties'] ?? [] as $key => $party) {
            $this->assertParty($key, $party);
        }

        foreach ($expect['money_accounts'] ?? [] as $key => $balance) {
            expect(ledgerBalance($this->accounts[$key]->ledgerAccount->code))->toBe($balance, "{$label}.money_accounts.{$key}");
        }
    }

    /**
     * A journal's posting date, source type and lines (account, party key, debit, credit).
     *
     * @param  array<string, mixed>  $expected
     */
    private function assertJournal(JournalEntry $journal, array $expected, string $label): void
    {
        $partyKeys = array_flip(array_map(fn (Party $party): int => $party->id, $this->parties));
        $lines = array_map(fn (array $line): array => [
            'account' => $line[0],
            'party' => $line[3] === null ? null : $partyKeys[Party::query()->where('code', $line[3])->sole()->id],
            'debit' => $line[1],
            'credit' => $line[2],
        ], journalLines($journal->id));

        expect($journal->posting_date)->toBe($expected['posting_date'], "{$label}.posting_date")
            ->and($lines)->toBe($expected['lines'], "{$label}.lines");

        if (isset($expected['source_type'])) {
            expect($journal->source_type)->toBe($expected['source_type'], "{$label}.source_type");
        }
    }

    /**
     * `cash_shortage`: PR-23 by the Owner (checked in `expected.cash_shortage_journal`).
     *
     * @param  array<string, mixed>  $entry
     */
    private function cashShortage(User $by, array $entry): null
    {
        app(PostCashDifference::class)->handle($by, $entry['period'], $this->accounts[$entry['money_account']], $entry['amount'], PostCashDifference::SHORTAGE, $entry['note']);

        return null;
    }

    /**
     * `close_month`: closes, or fails with exactly the expected failing checks.
     *
     * @param  array<string, mixed>  $entry
     */
    private function closeMonth(User $by, array $entry): null
    {
        $result = app(CloseMonth::class)->handle($by, $entry['period'], (bool) ($entry['accept_pending_rates'] ?? false));
        $accountKeys = array_flip(array_map(fn (MoneyAccount $account): int => $account->id, $this->accounts));

        expect($result->closed)->toBe($entry['expect']['closed'], "close_month {$entry['period']}");

        if (isset($entry['expect']['failing_checks'])) {
            $failing = array_map(fn (MonthEndCheck $check): array => array_filter([
                'check' => $check->check,
                'money_account' => isset($check->details['money_account_id']) ? $accountKeys[$check->details['money_account_id']] : null,
                'difference' => $check->details['difference'] ?? null,
            ], fn ($value) => $value !== null), $result->failing());

            expect($failing)->toBe($entry['expect']['failing_checks'], "close_month {$entry['period']} failing checks");
        }

        return null;
    }

    private function setToday(string $date): null
    {
        freezeToday($date);

        return null;
    }
}
