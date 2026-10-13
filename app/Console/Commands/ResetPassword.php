<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * BR-USR-09: emergency password reset on the server. Prints a temporary password
 * once, forces a change at the next login (BR-USR-08) and ends the user's sessions.
 */
class ResetPassword extends Command
{
    protected $signature = 'zb:reset-password {email}';

    protected $description = 'Give a user a temporary password (server only)';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No user has this e-mail address.');

            return self::FAILURE;
        }

        // 16 characters with letters, numbers and symbols, so it passes Password::defaults().
        $password = Str::password(16);

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => true,
            ])->save();

            AuditLogger::log(AuditActions::SERVER_COMMAND, $user, [], ['command' => 'zb:reset-password', 'user_id' => $user->id]);
            Sessions::endFor($user);
        });

        $this->line("Temporary password for {$user->name}: {$password}");
        $this->line('It is shown only once. The user must choose a new password at the next login.');

        return self::SUCCESS;
    }
}
