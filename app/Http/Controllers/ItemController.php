<?php

namespace App\Http\Controllers;

use App\Domain\Items\Actions\CreateItem;
use App\Domain\Items\Actions\DeactivateItem;
use App\Domain\Items\Actions\UpdateItem;
use App\Domain\Items\Models\Item;
use App\Http\Requests\Items\StoreItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page 45, Items (/settings/items). */
class ItemController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', Item::class);

        $items = Item::query()
            ->with(['purchaseAccount', 'salesAccount'])
            ->orderBy('id')
            ->get()
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'public_id' => $item->public_id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type->value,
                'account_code' => ($item->purchaseAccount ?? $item->salesAccount)?->code,
                'is_system' => $item->is_system,
                'is_active' => $item->is_active,
                'has_entries' => $item->hasEntries(),
            ]);

        return Inertia::render('items/index', ['items' => $items]);
    }

    public function store(StoreItemRequest $request, CreateItem $action): RedirectResponse
    {
        $this->authorize('create', Item::class);

        /** @var array{code: string, name: string, type: string} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $data);

        Flash::success(__('Saved.'));

        return to_route('items.index');
    }

    public function update(UpdateItemRequest $request, Item $item, UpdateItem $action): RedirectResponse
    {
        $this->authorize('update', $item);

        /** @var array{name: string, type: string} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $item, $data);

        Flash::success(__('Saved.'));

        return to_route('items.index');
    }

    public function deactivate(Request $request, Item $item, DeactivateItem $action): RedirectResponse
    {
        $this->authorize('deactivate', $item);

        $action->handle($this->actor($request), $item);

        Flash::success(__('Saved.'));

        return to_route('items.index');
    }
}
