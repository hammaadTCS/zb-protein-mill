<?php

declare(strict_types=1);

namespace App\Domain\Reports\Documents;

use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Reports\Queries\PartyKhata;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Format;

/**
 * Spec §13.1 party statement (D-35): the khata for a period, without cancelled
 * entries, payments awaiting approval or internal notes. The English name only
 * (spec §13: the PDF library cannot join Urdu letters).
 */
final class PartyStatement
{
    /**
     * @return array<string, mixed> the view data of `pdf.statement`
     */
    public static function data(Party $party, string $from, string $to, User $by): array
    {
        $khata = PartyKhata::for($party, $from, $to);

        // A journal and its reversal (a cancelled entry, or the old version of an edited
        // one) both inside the period are left out together; the running balance is
        // recomputed from the opening so it still adds up.
        $journals = [];

        foreach ($khata->rows as $row) {
            if ($row->journalId !== null) {
                $journals[$row->journalId] = true;
            }
        }

        $hidden = [];

        foreach ($khata->rows as $row) {
            if ($row->reversalOfId !== null && isset($journals[$row->reversalOfId])) {
                $hidden[$row->journalId] = true;
                $hidden[$row->reversalOfId] = true;
            }
        }

        $increaseIsDebit = in_array($party->type, [PartyType::Buyer, PartyType::Employee], true);
        $net = self::net($khata->opening->side, $khata->opening->amount);
        $rows = [];

        foreach ($khata->rows as $row) {
            if ($row->awaitingApproval || ($row->journalId !== null && isset($hidden[$row->journalId]))) {
                continue;
            }

            $increase = $row->increase ?? '0.00';
            $decrease = $row->decrease ?? '0.00';
            $net = Decimal::add($net, $increaseIsDebit ? Decimal::sub($increase, $decrease) : Decimal::sub($decrease, $increase));
            $balance = $party->balanceOfNet($net);

            $rows[] = [
                'date' => Format::date($row->date),
                'voucher' => $row->voucher,
                'description' => $row->description, // a rate-pending entry reads "Waste 800 kg – Rate pending"
                'increase' => $row->increase === null ? null : Format::money($row->increase),
                'decrease' => $row->decrease === null ? null : Format::money($row->decrease),
                'balance' => self::words($balance->words, $balance->amount),
            ];
        }

        return [
            'company' => (string) Settings::get(SettingKeys::COMPANY_NAME),
            'logo' => self::logo(),
            'party' => ['name' => $party->name, 'code' => $party->code, 'mobile' => $party->mobile],
            'from' => Format::date($from),
            'to' => Format::date($to),
            'opening' => self::words($khata->opening->words, $khata->opening->amount),
            'rows' => $rows,
            'closing' => self::words($party->balanceOfNet($net)->words, $party->balanceOfNet($net)->amount),
            'generated' => 'Generated on '.Format::now().' by '.$by->name,
        ];
    }

    /** The logo for PDFs, inline (remote file access is off). */
    public static function logo(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/zb-logo-96.png')));
    }

    /** "We owe Rs 52,000.00", or "Settled". */
    public static function words(string $words, string $amount): string
    {
        return $words === 'Settled' ? 'Settled' : $words.' '.Format::money($amount);
    }

    private static function net(string $side, string $amount): string
    {
        return match ($side) {
            'debit' => $amount,
            'credit' => Decimal::negate($amount),
            default => '0.00',
        };
    }
}
