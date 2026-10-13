<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Users\Owners;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Support\Facades\DB;

/** Page 43: a disabled user cannot log in (BR-USR-03); their sessions end (BR-SEC-13). */
final class DisableUser
{
    public function handle(User $by, User $user): User
    {
        return DB::transaction(function () use ($by, $user): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $user->is_active) {
                return $user;
            }

            Owners::assertNotLast($user); // BR-USR-04

            $user->forceFill(['is_active' => false])->save();
            AuditLogger::log(AuditActions::USER_CHANGED, $user, ['is_active' => true], ['is_active' => false], by: $by);
            Sessions::endFor($user);

            return $user;
        });
    }
}
