<?php

declare(strict_types=1);

namespace App\Domain\Items\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Items\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BR-GEN-08: an inactive item cannot be picked for new entries; it stays in history
 * and reports. Its ledger account stays active, so old entries can still be edited.
 */
final class DeactivateItem
{
    public function handle(User $by, Item $item): Item
    {
        return DB::transaction(function () use ($by, $item): Item {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);

            if (! $item->is_active) {
                return $item;
            }

            $item->update(['is_active' => false, 'updated_by' => $by->id]);

            AuditLogger::log(AuditActions::UPDATED, $item, ['is_active' => true], ['is_active' => false], by: $by);

            return $item;
        });
    }
}
