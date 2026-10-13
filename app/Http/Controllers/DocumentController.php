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
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Spec §13, D-35: the party statement and entry slip PDFs, shared with the party. */
class DocumentController extends Controller
{
    /** §13.1: `?from=&to=`, by default from the system start date to today. */
    public function statement(Request $request, Party $party): Response
    {
        $this->authorize('view', $party);

        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);
        $from = $range['from'] ?? (is_string($start) ? $start : Today::date());
        $to = $range['to'] ?? Today::date();

        return PdfDocument::download(
            'pdf.statement',
            PartyStatement::data($party, $from, $to, $request->user()),
            "statement-{$party->code}-{$from}-{$to}.pdf",
        );
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
