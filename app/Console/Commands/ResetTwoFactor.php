<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** BR-USR-09: turns off a user's two-step login on the server and ends their sessions. */
class ResetTwoFactor extends Command
{
    protected $signature = 'zb:reset-two-factor {email}';

    protected $description = "Turn off a user's two-step login (server only)";

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No user has this e-mail address.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            AuditLogger::log(AuditActions::SERVER_COMMAND, $user, [], ['command' => 'zb:reset-two-factor', 'user_id' => $user->id]);
            Sessions::endFor($user);
        });

        $this->info("Two-step login turned off for {$user->name}.");

        return self::SUCCESS;
    }
}
