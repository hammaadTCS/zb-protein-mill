<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * BR-GEN-11, ADR-0011 (conventions §2a): every create form sends a one-time UUID.
 * A second save with the same key (double tap, retry on a slow phone) saves
 * nothing new; the user sees "Already saved. {voucher}".
 */
final class Idempotency
{
    /**
     * The entry this user already saved with this key, in the user's company.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass  a model with company_id, idempotency_key and created_by
     * @return TModel|null
     */
    public static function existing(string $modelClass, User $user, string $key): ?Model
    {
        return $modelClass::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $user->company_id)
            ->where('created_by', $user->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * True when the insert failed only because another request with the same key
     * was saved at the same moment (unique index on company_id + idempotency_key).
     *
     * MariaDB reports it as SQLSTATE 23000 with driver code 1062 (ER_DUP_ENTRY) and
     * the message "Duplicate entry '…' for key '<index>'"; the index name is what
     * tells an idempotency clash from any other unique rule (Q-11, conventions §14b).
     */
    public static function isDuplicateKeyViolation(QueryException $e): bool
    {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            return false;
        }

        return preg_match("/for key '([^']+)'/", $e->getMessage(), $m) === 1
            && str_ends_with($m[1], '_idempotency_key_unique');
    }

    /** The glossary text for a repeated save. */
    public static function alreadySavedMessage(string $voucherNo): string
    {
        return __('Already saved. :voucher', ['voucher' => $voucherNo]);
    }
}
