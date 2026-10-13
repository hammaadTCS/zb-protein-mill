<?php

declare(strict_types=1);

namespace App\Support;

use Inertia\Inertia;

/**
 * The one-time messages after a save (conventions §10: flash.success, flash.voucher;
 * spec §15.5: a toast showing the voucher number).
 */
final class Flash
{
    /** Glossary: "Saved. {voucher number}". */
    public static function saved(string $voucherNo): void
    {
        Inertia::flash(['success' => __('Saved. :voucher', ['voucher' => $voucherNo]), 'voucher' => $voucherNo]);
    }

    /** Glossary: "Already saved. {voucher number}" (BR-GEN-11). */
    public static function alreadySaved(string $voucherNo): void
    {
        Inertia::flash(['success' => Idempotency::alreadySavedMessage($voucherNo), 'voucher' => $voucherNo]);
    }

    /** Any other confirmation in glossary words. */
    public static function success(string $message): void
    {
        Inertia::flash(['success' => $message]);
    }
}
