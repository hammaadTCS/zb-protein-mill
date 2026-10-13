<?php

namespace App\Providers;

use App\Domain\Audit\AuthenticationAuditor;
use App\Domain\Settings\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
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
            'setting' => Setting::class,
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
