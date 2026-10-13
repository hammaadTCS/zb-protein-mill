<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BR-SEC-13: end a user's sessions at once (password changed or reset, user
 * disabled, role changed). Sessions are framework housekeeping rows (database
 * session driver), the only kind of row the app may delete.
 */
final class Sessions
{
    /**
     * Ends every session of the user except the given one, and replaces the
     * "remember me" token so no other device can log back in with an old cookie.
     *
     * @return int how many sessions were ended
     */
    public static function endFor(User $user, ?string $exceptSessionId = null): int
    {
        $ended = DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete(); // guard:allow sessions are housekeeping (BR-SEC-13)

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if ($ended > 0) {
            AuditLogger::log(AuditActions::SESSIONS_ENDED, $user, new: ['count' => $ended]);
        }

        return $ended;
    }
}
