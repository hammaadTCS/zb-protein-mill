<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/**
 * The action names written to audit_logs (card T1.8, spec BR-AUD-01).
 * AuditLogger refuses any other name, so a typo can never create a new kind of row.
 */
final class AuditActions
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const CANCELLED = 'cancelled';

    public const RATE_ADDED = 'rate_added';

    public const RATE_CHANGED = 'rate_changed';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REVIEWED = 'reviewed';

    public const MERGED = 'merged';

    public const BANK_DETAILS_CHANGED = 'bank_details_changed';

    public const SETTING_CHANGED = 'setting_changed';

    public const USER_CREATED = 'user_created';

    public const USER_CHANGED = 'user_changed';

    public const MONTH_CLOSED = 'month_closed';

    public const MONTH_REOPENED = 'month_reopened';

    public const OPENING_COMPLETED = 'opening_completed';

    public const WARNING_CONFIRMED = 'warning_confirmed';

    public const CASH_COUNT_ENTERED = 'cash_count_entered';

    public const BACKUP_DOWNLOADED = 'backup_downloaded';

    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGOUT = 'logout';

    /** BR-AUD-01, BR-USR-07, BR-USR-09, conventions §12: zb:* commands that change users. */
    public const SERVER_COMMAND = 'server_command';

    /** BR-AUD-01: "user sessions ended" (BR-SEC-13). */
    public const SESSIONS_ENDED = 'sessions_ended';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
