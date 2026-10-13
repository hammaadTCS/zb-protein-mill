<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Users\Owners;
use App\Models\User;
use App\Support\Sessions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/** Page 43: change a user's role; their sessions end (BR-SEC-13). Never the last Owner (BR-USR-04). */
final class UpdateUserRole
{
    public function handle(User $by, User $user, string $role): User
    {
        return DB::transaction(function () use ($by, $user, $role): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $old = (string) $user->getRoleNames()->first();

            if ($old === $role) {
                return $user;
            }

            if ($old === RolesAndPermissionsSeeder::OWNER) {
                Owners::assertNotLast($user, 'role'); // BR-USR-04
            }

            $user->syncRoles([$role]);
            AuditLogger::log(AuditActions::USER_CHANGED, $user, ['role' => $old], ['role' => $role], by: $by);
            Sessions::endFor($user); // BR-SEC-13

            return $user;
        });
    }
}
