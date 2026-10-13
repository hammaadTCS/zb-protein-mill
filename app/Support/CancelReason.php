<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** BR-GEN-04: cancelling needs a reason of at least 5 characters. */
final class CancelReason
{
    /** Returns the trimmed reason, or refuses it on the `reason` field. */
    public static function check(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => __('Give a reason of at least 5 characters.')]);
        }

        return $reason;
    }
}
