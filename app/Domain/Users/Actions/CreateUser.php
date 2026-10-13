<?php

declare(strict_types=1);

namespace App\Domain\Users\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Page 43: the Owner adds a user with a role and a temporary password, which the user
 * must change before any other page opens (BR-USR-08).
 */
final class CreateUser
{
    /**
     * @param  array{name: string, email: string, role: string, password: string}  $data  validated fields
     */
    public function handle(User $by, array $data): User
    {
        return DB::transaction(function () use ($by, $data): User {
            $user = new User;
            $user->forceFill([
                'company_id' => $by->company_id,
                'name' => trim($data['name']),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'must_change_password' => true, // BR-USR-08
            ])->save();
            $user->assignRole($data['role']);

            AuditLogger::log(AuditActions::USER_CREATED, $user, [], [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $data['role'],
            ], by: $by);

            return $user;
        });
    }
}
