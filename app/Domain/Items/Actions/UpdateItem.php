<?php

declare(strict_types=1);

namespace App\Domain\Items\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Items\Enums\ItemType;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Name, and type while the item has no entries (BR-ITM-02). The four system items
 * never change: their ledger accounts are system accounts (BR-LED-01).
 */
final class UpdateItem
{
    /**
     * @param  array{name: string, type: string}  $data
     */
    public function handle(User $by, Item $item, array $data): Item
    {
        return DB::transaction(function () use ($by, $item, $data): Item {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            $type = ItemType::from($data['type']);

            if ($item->is_system && ($type !== $item->type || $data['name'] !== $item->name)) {
                throw ValidationException::withMessages(['name' => __('System items cannot be changed.')]);
            }

            if ($type !== $item->type && $item->hasEntries()) {
                throw ValidationException::withMessages(['type' => __('Type cannot change after the first entry.')]);
            }

            $account = $this->account($item);
            $old = CreateItem::audited($item, $account);

            if ($type->isBought() !== $item->type->isBought()) {
                // Bought <-> sold needs the other kind of ledger account. The old one has
                // no entries (the item has none), so it is made inactive.
                $account->update(['is_active' => false, 'updated_by' => $by->id]);
                $account = CreateItem::createLedgerAccount($by, $type, $data['name']);
                $item->purchase_ledger_account_id = $type->isBought() ? $account->id : null;
                $item->sales_ledger_account_id = $type->isBought() ? null : $account->id;
            }

            $item->fill(['name' => $data['name'], 'type' => $type, 'updated_by' => $by->id]);

            if (! $item->isDirty()) {
                return $item;
            }

            $item->save();
            $account->update(['name' => CreateItem::ledgerName($type, $item->name), 'updated_by' => $by->id]);

            AuditLogger::log(AuditActions::UPDATED, $item, $old, CreateItem::audited($item, $account), by: $by);

            return $item;
        });
    }

    private function account(Item $item): LedgerAccount
    {
        return LedgerAccount::query()->lockForUpdate()->findOrFail($item->ledgerAccountId());
    }
}
