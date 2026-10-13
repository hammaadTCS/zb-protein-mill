<?php

declare(strict_types=1);

namespace App\Domain\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

/** BR-USR-04: at least one active Owner must always exist. */
final class Owners
{
    /** Refuses when this user is the company's last active Owner (glossary "Last owner"). */
    public static function assertNotLast(User $user, string $field = 'user'): void
    {
        if (! $user->is_active || ! $user->hasRole(RolesAndPermissionsSeeder::OWNER)) {
            return;
        }

        $activeOwners = User::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->role(RolesAndPermissionsSeeder::OWNER)
            ->count();

        if ($activeOwners <= 1) {
            throw ValidationException::withMessages([$field => __('At least one active owner is required.')]);
        }
    }
}
