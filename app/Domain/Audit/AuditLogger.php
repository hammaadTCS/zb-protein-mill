<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * BR-AUD-01: writes one permanent audit row with user, time, IP address and device.
 */
final class AuditLogger
{
    /** True only while this class writes a row. Never set from outside. */
    private static bool $writing = false;

    public static function isWriting(): bool
    {
        return self::$writing;
    }

    /**
     * @param  string  $action  one of AuditActions
     * @param  array<string, mixed>  $old  previous values of the changed fields
     * @param  array<string, mixed>  $new  new values of the changed fields
     * @param  User|null  $by  null = the logged-in user, if any
     */
    public static function log(
        string $action,
        ?Model $auditable,
        array $old = [],
        array $new = [],
        ?string $reason = null,
        ?User $by = null,
    ): AuditLog {
        if (! in_array($action, AuditActions::all(), true)) {
            throw new InvalidArgumentException("Unknown audit action {$action}.");
        }

        $loggedIn = auth()->user();
        $by ??= $loggedIn instanceof User ? $loggedIn : null;
        $request = request(); // in console commands: an empty request, so IP and device are null
        $companyId = $by !== null ? $by->company_id : self::companyOf($auditable);

        self::$writing = true;

        try {
            return AuditLog::query()->create([
                'company_id' => $companyId,
                'user_id' => $by?->id,
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $old === [] ? null : $old,
                'new_values' => $new === [] ? null : $new,
                'reason' => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } finally {
            self::$writing = false;
        }
    }

    private static function companyOf(?Model $auditable): int
    {
        $companyId = $auditable?->getAttribute('company_id');

        return is_numeric($companyId) ? (int) $companyId : CurrentCompany::id();
    }
}
