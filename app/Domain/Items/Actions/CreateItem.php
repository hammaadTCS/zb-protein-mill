<?php

declare(strict_types=1);

namespace App\Domain\Items\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Items\Enums\ItemType;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\AccountCodeAllocator;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BR-ITM-02: the Owner adds an item; the system creates its ledger account in the
 * same transaction.
 */
final class CreateItem
{
    /**
     * @param  array{code: string, name: string, type: string}  $data
     */
    public function handle(User $by, array $data): Item
    {
        return DB::transaction(function () use ($by, $data): Item {
            $type = ItemType::from($data['type']);
            $account = self::createLedgerAccount($by, $type, $data['name']);

            $item = Item::query()->create([
                'company_id' => $by->company_id,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $type,
                'unit' => 'kg',
                'purchase_ledger_account_id' => $type->isBought() ? $account->id : null,
                'sales_ledger_account_id' => $type->isBought() ? null : $account->id,
                'created_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CREATED, $item, [], self::audited($item, $account), by: $by);

            return $item;
        });
    }

    /**
     * Raw material and fuel: "Purchases – {name}", expense, debit (range 5020+).
     * Product: "Sales – {name}", income, credit (range 4020+).
     */
    public static function createLedgerAccount(User $by, ItemType $type, string $name): LedgerAccount
    {
        return LedgerAccount::query()->create([
            'company_id' => $by->company_id,
            'code' => AccountCodeAllocator::next($type->isBought() ? 'purchase_item' : 'sales_item'),
            'name' => self::ledgerName($type, $name),
            'type' => $type->isBought() ? AccountType::Expense : AccountType::Income,
            'normal_side' => $type->isBought() ? NormalSide::Debit : NormalSide::Credit,
            'is_system' => false,
            'created_by' => $by->id,
        ]);
    }

    public static function ledgerName(ItemType $type, string $name): string
    {
        return ($type->isBought() ? 'Purchases – ' : 'Sales – ').$name;
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Item $item, LedgerAccount $account): array
    {
        return [
            'code' => $item->code,
            'name' => $item->name,
            'type' => $item->type->value,
            'ledger_account' => $account->code,
            'is_active' => $item->is_active,
        ];
    }
}
