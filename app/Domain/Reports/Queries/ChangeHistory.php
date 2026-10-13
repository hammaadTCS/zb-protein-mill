<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateChange;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * BR-AUD-04, spec §11.6: what changed on one record, newest first: date and time,
 * user, action, each changed field "old → new", and the reason. Rate history comes
 * from rate_changes (BR-PUR-09).
 */
final class ChangeHistory
{
    /** Screen words for the audit actions. */
    private const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Edited',
        'cancelled' => 'Cancelled',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'reviewed' => 'Marked reviewed',
        'merged' => 'Merged',
        'bank_details_changed' => 'Bank details changed',
        'warning_confirmed' => 'Warning confirmed',
    ];

    /** Field ids shown with the record's name. */
    private const NAMED = [
        'party_id' => Party::class,
        'handled_by_party_id' => Party::class,
        'item_id' => Item::class,
        'money_account_id' => MoneyAccount::class,
        'category_id' => ExpenseCategory::class,
    ];

    /** Linked entry ids shown with their voucher number. */
    private const VOUCHERS = [
        'linked_purchase_id' => Purchase::class,
        'linked_sale_id' => Sale::class,
    ];

    /**
     * @return list<array{at: string, user: string|null, action: string, changes: list<array{field: string, old: string|null, new: string|null}>, reason: string|null}>
     */
    public static function for(Model $record): array
    {
        $rows = [];

        $logs = AuditLog::query()
            ->with('user')
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->whereNotIn('action', ['rate_added', 'rate_changed']) // shown from rate_changes below
            ->get();

        foreach ($logs as $log) {
            $rows[] = [
                'sort' => [(string) $log->created_at?->format('Y-m-d H:i:s.u'), 1, $log->id],
                'at' => (string) $log->created_at?->toIso8601String(),
                'user' => $log->user?->name,
                'action' => self::ACTIONS[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)),
                'changes' => $log->action === 'warning_confirmed'
                    ? [['field' => 'warning', 'old' => null, 'new' => (string) ($log->new_values['code'] ?? '')]]
                    : self::changes($log->old_values ?? [], $log->new_values ?? [], $log->action === 'created'),
                'reason' => $log->reason,
            ];
        }

        $rates = RateChange::query()
            ->where('target_type', $record->getMorphClass())
            ->where('target_id', $record->getKey())
            ->get();

        foreach ($rates as $change) {
            $rows[] = [
                'sort' => [$change->changed_at->format('Y-m-d H:i:s.u'), 0, $change->id],
                'at' => $change->changed_at->toIso8601String(),
                'user' => self::userName($change->changed_by),
                'action' => $change->old_rate === null ? 'Rate added' : 'Rate changed',
                'changes' => [
                    ['field' => 'rate', 'old' => $change->old_rate, 'new' => $change->new_rate],
                    ['field' => 'amount', 'old' => $change->old_amount, 'new' => $change->new_amount],
                ],
                'reason' => $change->reason,
            ];
        }

        // Newest first; within the same moment, the audit row of a save before its rate row.
        usort($rows, fn (array $a, array $b): int => $b['sort'] <=> $a['sort']);

        return array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private static function changes(array $old, array $new, bool $created): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $before = self::text($field, $old[$field] ?? null);
            $after = self::text($field, $new[$field] ?? null);

            if (! $created && $before === $after) {
                continue;
            }

            if ($created && $after === null) {
                continue;
            }

            $changes[] = ['field' => str_replace(['_party_id', '_id', '_'], ['', '', ' '], (string) $field), 'old' => $created ? null : $before, 'new' => $after];
        }

        return $changes;
    }

    private static function text(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::NAMED[$field]) && is_numeric($value)) {
            $model = self::NAMED[$field];

            return (string) ($model::query()->withoutGlobalScopes()->whereKey((int) $value)->value('name') ?? $value);
        }

        if (isset(self::VOUCHERS[$field]) && is_numeric($value)) {
            $model = self::VOUCHERS[$field];

            return (string) ($model::query()->withoutGlobalScopes()->whereKey((int) $value)->value('voucher_no') ?? $value);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }

    private static function userName(int $userId): ?string
    {
        $name = User::query()->whereKey($userId)->value('name');

        return $name === null ? null : (string) $name;
    }
}
