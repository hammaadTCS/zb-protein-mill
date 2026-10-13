<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Parties\Models\Party;
use App\Http\Controllers\PartyController;
use App\Support\Today;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Spec §11.2: the filters, paging and party option every entry list shares. */
trait ListsEntries
{
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
            'party_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in($statuses)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $today = Today::date();

        return [
            'from' => $input['from'] ?? substr($today, 0, 8).'01',
            'to' => $input['to'] ?? $today,
            'party_id' => isset($input['party_id']) ? (int) $input['party_id'] : null,
            'status' => (string) ($input['status'] ?? ''),
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }

    /**
     * The picker option of the party filter, so the page can show its label.
     *
     * @return array{id: int, code: string, type: string, label: string}|null
     */
    protected function partyOption(?int $partyId): ?array
    {
        $party = $partyId === null ? null : Party::query()->find($partyId);

        return $party === null ? null : ['id' => $party->id, 'code' => $party->code, 'type' => $party->type->value, 'label' => PartyController::label($party)];
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
