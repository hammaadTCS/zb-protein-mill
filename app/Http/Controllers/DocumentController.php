<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Documents\EntrySlip;
use App\Domain\Reports\Documents\PartyStatement;
use App\Domain\Reports\Documents\PdfDocument;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Dates;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Spec §13, D-35: the party statement and entry slip PDFs, shared with the party. */
class DocumentController extends Controller
{
    /**
     * §13.1: `?from=&to=`. BR-RPT-11: one PDF covers at most 3 months; without a
     * chosen period it covers the last 3 months up to `to` (today by default), never
     * before the system start date. The balance before that is the opening line.
     */
    public function statement(Request $request, Party $party): Response
    {
        $this->authorize('view', $party);

        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = $range['to'] ?? Today::date();
        $from = $range['from'] ?? self::lastThreeMonths($to);

        if (Dates::longerThanThreeMonths($from, $to)) {
            throw ValidationException::withMessages([
                'to' => __('A statement PDF can cover at most 3 months. Choose a shorter period.'),
            ]);
        }

        return PdfDocument::download(
            'pdf.statement',
            PartyStatement::data($party, $from, $to, $request->user()),
            "statement-{$party->code}-{$from}-{$to}.pdf",
        );
    }

    /** The first day of the 3 months that end on $to, or the system start date if later. */
    private static function lastThreeMonths(string $to): string
    {
        $from = Dates::threeMonthsBefore($to);
        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);

        return is_string($start) && $start > $from ? $start : $from;
    }

    /** §13.2 purchase slip. */
    public function purchaseSlip(Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);

        return PdfDocument::download('pdf.slip', EntrySlip::data($purchase), "{$purchase->voucher_no}.pdf");
    }

    /** §13.2 sale slip. */
    public function saleSlip(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        return PdfDocument::download('pdf.slip', EntrySlip::data($sale), "{$sale->voucher_no}.pdf");
    }
}
