<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * BR-GEN-05: gapless running numbers, never reused.
 *
 * The number is taken inside the saving transaction with a row lock, so a failed
 * save gives its number back and two saves at the same moment wait for each other.
 */
final class VoucherNumberService
{
    /** conventions §8: doc type => [prefix, width] */
    public const DOC_TYPES = [
        'purchase' => ['PUR', 6],
        'sale' => ['SAL', 6],
        'adjustment' => ['ADJ', 6],
        'payment_paid' => ['PAY', 6],
        'payment_received' => ['RCV', 6],
        'expense' => ['EXP', 6],
        'partner_transaction' => ['PTN', 6],
        'transfer' => ['TRF', 6],
        'opening_balance' => ['OPB', 6],
        'rate_correction' => ['RCO', 6],
        'cash_adjustment' => ['CSH', 6],
        'journal' => ['JV', 6],
        'party_supplier' => ['S', 4],
        'party_buyer' => ['B', 4],
        'party_partner' => ['P', 4],
        'party_employee' => ['E', 4],
        'party_other' => ['O', 4],
    ];

    /**
     * @throws LogicException outside a database transaction
     * @throws InvalidArgumentException for an unknown doc type
     */
    public static function next(string $docType): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Voucher numbers can only be taken inside a database transaction.');
        }

        [$prefix, $width] = self::DOC_TYPES[$docType] ?? throw new InvalidArgumentException("Unknown doc type {$docType}.");
        $companyId = CurrentCompany::id();

        // First use: create the sequence. INSERT IGNORE keeps two first saves from colliding
        // (MariaDB's insert-or-skip; conventions §14b, the PostgreSQL upsert-ignore row).
        DB::table('voucher_sequences')->insertOrIgnore([
            'company_id' => $companyId,
            'doc_type' => $docType,
            'prefix' => $prefix,
            'width' => $width,
            'next_number' => 1,
        ]);

        $sequence = DB::table('voucher_sequences')
            ->where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->lockForUpdate()
            ->first(['prefix', 'width', 'next_number']);

        if ($sequence === null) {
            throw new LogicException("Voucher sequence {$docType} could not be read.");
        }

        DB::table('voucher_sequences')
            ->where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->update(['next_number' => $sequence->next_number + 1]);

        return $sequence->prefix.'-'.str_pad((string) $sequence->next_number, (int) $sequence->width, '0', STR_PAD_LEFT);
    }
}
