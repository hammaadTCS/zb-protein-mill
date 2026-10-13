<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Actions\CancelAdjustment;
use App\Domain\Adjustments\Actions\CreateAdjustment;
use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Http\Requests\Adjustments\StoreAdjustmentRequest;
use App\Http\Requests\CancelEntryRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/** The "Add agreed adjustment" dialog on purchase and sale detail pages (spec §11.7). */
class AdjustmentController extends Controller
{
    public function storeForPurchase(StoreAdjustmentRequest $request, Purchase $purchase, CreateAdjustment $action): RedirectResponse
    {
        $this->authorize('view', $purchase);

        return $this->store($request, $purchase, $action);
    }

    public function storeForSale(StoreAdjustmentRequest $request, Sale $sale, CreateAdjustment $action): RedirectResponse
    {
        $this->authorize('view', $sale);

        return $this->store($request, $sale, $action);
    }

    public function cancel(CancelEntryRequest $request, Adjustment $adjustment, CancelAdjustment $action): RedirectResponse
    {
        $this->authorize('cancel', $adjustment);

        $action->handle($this->actor($request), $adjustment, (string) $request->validated('reason'));
        Flash::saved($adjustment->voucher_no);

        return back();
    }

    private function store(StoreAdjustmentRequest $request, Purchase|Sale $target, CreateAdjustment $action): RedirectResponse
    {
        $this->authorize('create', Adjustment::class);

        $adjustment = $action->handle($this->actor($request), $target, $request->validated());
        $adjustment->wasRecentlyCreated ? Flash::saved($adjustment->voucher_no) : Flash::alreadySaved($adjustment->voucher_no);

        return back();
    }
}
