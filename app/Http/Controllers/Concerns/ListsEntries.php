<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Http\Controllers\PartyController;
use App\Models\User;
use App\Support\Today;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Spec §11.2: the filters, paging and party option every entry list shares.
 * BR-SEC-19: the address bar names a filtered record by its public id; the queries use
 * the internal id it stands for, and shownFilters() turns it back for the page.
 */
trait ListsEntries
{
    /** Filter keys that name a record, and the model each one names. */
    private const RECORD_FILTERS = [
        'party_id' => Party::class,
        'category_id' => ExpenseCategory::class,
        'money_account_id' => MoneyAccount::class,
        'item_id' => Item::class,
        'user_id' => User::class,
    ];

    /**
     * Current month by default; date range, party, status and search text.
     *
     * @param  list<string>  $statuses
     * @return array{from: string, to: string, party_id: int|null, status: string, q: string}
     */
    protected function listFilters(Request $request, array $statuses): array
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'party_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in($statuses)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $today = Today::date();

        return [
            'from' => $input['from'] ?? substr($today, 0, 8).'01',
            'to' => $input['to'] ?? $today,
            'party_id' => Party::idFromPublic($input['party_id'] ?? null),
            'status' => (string) ($input['status'] ?? ''),
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }

    /**
     * The filters as the page shows and sends them back: each record filter by public id.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function shownFilters(array $filters): array
    {
        foreach (self::RECORD_FILTERS as $key => $model) {
            if (isset($filters[$key]) && is_int($filters[$key])) {
                $filters[$key] = $model::query()->whereKey($filters[$key])->value('public_id');
            }
        }

        return $filters;
    }

    /**
     * The picker option of the party filter, so the page can show its label.
     *
     * @return array{id: int, public_id: string, code: string, type: string, label: string}|null
     */
    protected function partyOption(?int $partyId): ?array
    {
        $party = $partyId === null ? null : Party::query()->find($partyId);

        return $party === null ? null : ['id' => $party->id, 'public_id' => $party->public_id, 'code' => $party->code, 'type' => $party->type->value, 'label' => PartyController::label($party)];
    }

    /**
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $page
     * @param  callable(TModel): array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function paginated(LengthAwarePaginator $page, callable $row): array
    {
        return [
            'data' => array_map($row, $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'prev_page_url' => $page->previousPageUrl(),
            'next_page_url' => $page->nextPageUrl(),
        ];
    }
}
