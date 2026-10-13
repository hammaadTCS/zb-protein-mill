<?php

namespace App\Http\Controllers;

use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/** Purchases (pages 3 to 5 come with T2.8). */
class PurchaseController extends Controller
{
    public function store(StorePurchaseRequest $request, CreatePurchase $action): RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());

        // BR-GEN-11: a repeated save shows "Already saved. {voucher}".
        $purchase->wasRecentlyCreated ? Flash::saved($purchase->voucher_no) : Flash::alreadySaved($purchase->voucher_no);

        return back();
    }
}
