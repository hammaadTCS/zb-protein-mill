<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Parties\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Page 41 (BR-AUD-03): who did what and when, newest first, 50 per page, with the
 * record each row is about.
 */
final class AuditTrail
{
    /** Record types whose detail page a row links to. */
    private const ROUTES = [
        'purchase' => 'purchases.show',
        'sale' => 'sales.show',
        'payment' => 'payments.show',
        'expense' => 'expenses.show',
        'party' => 'parties.show',
    ];

    /**
     * @param  array{user_id: int|null, action: string, from: string, to: string, type: string, q: string}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public static function page(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($filters['user_id'], fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['action'] !== '', fn (Builder $q) => $q->where('action', $filters['action']))
            ->when($filters['type'] !== '', fn (Builder $q) => $q->where('auditable_type', $filters['type']))
            ->where('created_at', '>=', $filters['from'].' 00:00:00+05')
            ->where('created_at', '<=', $filters['to'].' 23:59:59.999999+05') // Asia/Karachi days
            ->when($filters['q'] !== '', fn (Builder $q) => self::search($q, $filters['q']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
    }

    /**
     * @return array{at: string, user: string|null, action: string, record: array{type: string, label: string, url: string|null}|null, changes: list<array{field: string, old: string|null, new: string|null}>, reason: string|null, ip: string|null}
     */
    public static function row(AuditLog $log): array
    {
        return [
            'at' => (string) $log->created_at?->toIso8601String(),
            'user' => $log->user?->name,
            'action' => ChangeHistory::ACTIONS[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)),
            'record' => self::record($log),
            'changes' => self::changes($log),
            'reason' => $log->reason,
            'ip' => $log->ip_address,
        ];
    }

    /**
     * Old → new per field; a setting row shows the setting's key as the field.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    private static function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        if ($log->action === 'setting_changed' && isset($new['key'])) {
            return [['field' => str_replace('_', ' ', (string) $new['key']), 'old' => isset($old['value']) ? (string) $old['value'] : null, 'new' => isset($new['value']) ? (string) $new['value'] : null]];
        }

        return ChangeHistory::changes($old, $new, $log->action === 'created');
    }

    /**
     * Voucher number, or party name or code: the record's own, or its party's.
     *
     * @param  Builder<AuditLog>  $query
     */
    private static function search(Builder $query, string $text): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
        $partyIds = Party::query()->where(fn (Builder $q) => $q->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', [$like])->orWhereRaw('code COLLATE utf8mb4_unicode_ci LIKE ?', [$like]))->pluck('id')->all();

        $query->where(function (Builder $outer) use ($like, $partyIds): void {
            $outer->where(fn (Builder $q) => $q->where('auditable_type', 'party')->whereIn('auditable_id', $partyIds));

            foreach (['purchase', 'sale', 'payment', 'expense', 'adjustment', 'partner_transaction', 'transfer', 'rate_correction'] as $type) {
                $model = Relation::getMorphedModel($type);

                if ($model === null) {
                    continue;
                }

                /** @var Builder<Model> $records */
                $records = $model::query()->whereRaw('voucher_no COLLATE utf8mb4_unicode_ci LIKE ?', [$like]);

                if (in_array($type, ['purchase', 'sale', 'payment', 'expense', 'adjustment', 'partner_transaction'], true) && $partyIds !== []) {
                    $records->orWhereIn('party_id', $partyIds);
                }

                $ids = $records->pluck('id')->all();
                $outer->orWhere(fn (Builder $q) => $q->where('auditable_type', $type)->whereIn('auditable_id', $ids));
            }
        });
    }

    /**
     * @return array{type: string, label: string, url: string|null}|null
     */
    private static function record(AuditLog $log): ?array
    {
        if ($log->auditable_type === null) {
            return null;
        }

        $record = $log->auditable;
        $attributes = $record instanceof Model ? $record->getAttributes() : [];
        $label = (string) ($attributes['voucher_no'] ?? $attributes['name'] ?? $attributes['key'] ?? '#'.$log->auditable_id);
        $route = self::ROUTES[$log->auditable_type] ?? null;

        return [
            'type' => str_replace('_', ' ', $log->auditable_type),
            'label' => $label,
            'url' => $route !== null && $record instanceof Model ? route($route, $record, false) : null, // BR-SEC-19: by public id
        ];
    }
}
