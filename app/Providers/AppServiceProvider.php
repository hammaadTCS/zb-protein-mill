<?php

namespace App\Providers;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Audit\AuthenticationAuditor;
use App\Domain\Checks\Models\CashCount;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\Parties\Models\Party;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Models\Period;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\Models\Setting;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAudit();
    }

    /**
     * conventions §7: the short names stored in source_type, auditable_type and
     * attachable_type. Each module adds its model here when it is built.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'adjustment' => Adjustment::class,
            'cash_count' => CashCount::class,
            'expense' => Expense::class,
            'expense_category' => ExpenseCategory::class,
            'item' => Item::class,
            'money_account' => MoneyAccount::class,
            'opening_balance' => OpeningBalance::class,
            'partner_transaction' => PartnerTransaction::class,
            'party' => Party::class,
            'payment' => Payment::class,
            'period' => Period::class,
            'purchase' => Purchase::class,
            'rate_correction' => RateCorrection::class,
            'sale' => Sale::class,
            'setting' => Setting::class,
            'transfer' => Transfer::class,
            'user' => User::class,
        ]);
    }

    /**
     * BR-USR-05: login, failed login and logout are audited.
     */
    protected function configureAudit(): void
    {
        Event::listen(Login::class, [AuthenticationAuditor::class, 'login']);
        Event::listen(Failed::class, [AuthenticationAuditor::class, 'failed']);
        Event::listen(Logout::class, [AuthenticationAuditor::class, 'logout']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // BR-SEC-16: no lazy loading and no silently discarded attributes outside production.
        Model::shouldBeStrict(! app()->isProduction());

        // BR-OPS-06: /up fails when the database does not answer.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
