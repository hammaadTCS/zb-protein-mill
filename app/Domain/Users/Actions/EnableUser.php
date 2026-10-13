<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Page 43: a disabled user may log in again. */
final class EnableUser
{
    public function handle(User $by, User $user): User
    {
        return DB::transaction(function () use ($by, $user): User {
            if ($user->is_active) {
                return $user;
            }

            $user->forceFill(['is_active' => true])->save();
            AuditLogger::log(AuditActions::USER_CHANGED, $user, ['is_active' => false], ['is_active' => true], by: $by);

            return $user;
        });
    }
}
