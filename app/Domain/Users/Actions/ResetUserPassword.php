<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Page 43: the Owner types a new temporary password. The user must change it
 * (BR-USR-08) and every session of theirs ends (BR-SEC-13).
 */
final class ResetUserPassword
{
    public function handle(User $by, User $user, string $password): User
    {
        return DB::transaction(function () use ($by, $user, $password): User {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => true,
            ])->save();

            AuditLogger::log(AuditActions::USER_CHANGED, $user, [], ['password' => 'reset'], by: $by); // never the password itself
            Sessions::endFor($user);

            return $user;
        });
    }
}
