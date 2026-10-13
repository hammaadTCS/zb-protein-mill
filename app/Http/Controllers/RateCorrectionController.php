<?php

namespace App\Http\Controllers;

use App\Domain\Purchases\Actions\CreateRateCorrection;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Http\Requests\RateCorrectionRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/** "Correct rate" on the detail page of a purchase or sale in a closed month (BR-LED-09). */
class RateCorrectionController extends Controller
{
    public function storeForPurchase(RateCorrectionRequest $request, Purchase $purchase, CreateRateCorrection $action): RedirectResponse
    {
        $this->authorize('update', $purchase);

        return $this->store($request, $purchase, $action);
    }

    public function storeForSale(RateCorrectionRequest $request, Sale $sale, CreateRateCorrection $action): RedirectResponse
    {
        $this->authorize('update', $sale);

        return $this->store($request, $sale, $action);
    }

    private function store(RateCorrectionRequest $request, Purchase|Sale $entry, CreateRateCorrection $action): RedirectResponse
    {
        $correction = $action->handle(
            $this->actor($request),
            $entry,
            (string) $request->validated('rate'),
            (string) $request->validated('date'),
            (string) $request->validated('reason'),
        );
        Flash::saved($correction->voucher_no);

        return back();
    }
}
