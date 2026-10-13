<?php

namespace App\Http\Controllers;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Actions\ActivateParty;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\DeactivateParty;
use App\Domain\Parties\Actions\UpdateParty;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyBalance;
use App\Domain\Reports\Queries\KhataRow;
use App\Domain\Reports\Queries\PartyKhata;
use App\Http\Requests\Parties\StorePartyRequest;
use App\Http\Requests\Parties\UpdatePartyRequest;
use App\Support\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 17 and 18: parties list, add and edit; the party picker's search. */
class PartyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Party::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::enum(PartyType::class)],
            'status' => ['nullable', Rule::enum(PartyStatus::class)],
        ]);

        $parties = self::search(Party::query(), (string) ($filters['q'] ?? ''))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50) // spec §11.2
            ->withQueryString();

        /** @var list<Party> $page */
        $page = $parties->items();
        $ids = array_map(fn (Party $party): int => $party->id, $page);
        $nets = LedgerBalance::partyNets($ids);
        $pendingKg = Party::pendingKgFor($ids);

        return Inertia::render('parties/index', [
            'filters' => ['q' => $filters['q'] ?? '', 'type' => $filters['type'] ?? '', 'status' => $filters['status'] ?? ''],
            'parties' => [
                'data' => array_map(fn (Party $party): array => [
                    'id' => $party->id,
                    'code' => $party->code,
                    'name' => $party->name,
                    'area' => $party->area,
                    'mobile' => $party->mobile,
                    'type' => $party->type->value,
                    'status' => $party->status->value,
                    'needs_review' => $party->needs_review || $party->bank_change_needs_review,
                    'balance' => $party->balanceFrom($nets[$party->id] ?? [], $pendingKg[$party->id] ?? '0.000')->toArray(),
                ], $page),
                'current_page' => $parties->currentPage(),
                'last_page' => $parties->lastPage(),
                'total' => $parties->total(),
                'prev_page_url' => $parties->previousPageUrl(),
                'next_page_url' => $parties->nextPageUrl(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Party::class);

        return Inertia::render('parties/form', ['party' => null]);
    }

    public function store(StorePartyRequest $request, CreateParty $action): RedirectResponse
    {
        $this->authorize('create', Party::class);

        $party = $action->handle($this->actor($request), $request->partyData(), $request->confirmedWarnings());

        Flash::saved($party->code);

        return to_route('parties.index');
    }

    /** Page 19: party profile with the khata (R-01). */
    public function show(Request $request, Party $party): Response
    {
        $this->authorize('view', $party);

        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $khata = PartyKhata::for($party, $range['from'] ?? null, $range['to'] ?? null);
        $balance = $party->balance();
        $words = fn (PartyBalance $b): array => ['words' => $b->words, 'amount' => $b->amount];

        return Inertia::render('parties/show', [
            'party' => [
                'id' => $party->id,
                'code' => $party->code,
                'type' => $party->type->value,
                'status' => $party->status->value,
                'needs_review' => $party->needs_review || $party->bank_change_needs_review,
                'balance' => $balance->toArray(),
                'partner' => $balance->invested === null ? null : ['invested' => $balance->invested, 'withdrawn' => $balance->withdrawn, 'net' => $balance->net],
                ...array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $party->only(Party::FORM_FIELDS)),
            ],
            'khata' => [
                'from' => $khata->from,
                'to' => $khata->to,
                'opening' => $words($khata->opening),
                'closing' => $words($khata->closing),
                'rows' => array_map(fn (KhataRow $row): array => [
                    'date' => $row->date,
                    'voucher' => $row->voucher,
                    'description' => $row->description,
                    'increase' => $row->increase,
                    'decrease' => $row->decrease,
                    'balance' => $words($row->balance),
                    'route' => $row->route,
                ], $khata->rows),
            ],
        ]);
    }

    public function edit(Party $party): Response
    {
        $this->authorize('update', $party);

        return Inertia::render('parties/form', [
            'party' => [
                'id' => $party->id,
                'code' => $party->code,
                'status' => $party->status->value,
                'has_entries' => $party->hasEntries(),
                ...array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $party->only(Party::FORM_FIELDS)),
            ],
        ]);
    }

    public function update(UpdatePartyRequest $request, Party $party, UpdateParty $action): RedirectResponse
    {
        $this->authorize('update', $party);

        $party = $action->handle($this->actor($request), $party, $request->partyData(), $request->confirmedWarnings());

        Flash::saved($party->code);

        return to_route('parties.index');
    }

    public function deactivate(Request $request, Party $party, DeactivateParty $action): RedirectResponse
    {
        $this->authorize('changeStatus', $party);

        $action->handle($this->actor($request), $party);
        Flash::saved($party->code);

        return back();
    }

    public function activate(Request $request, Party $party, ActivateParty $action): RedirectResponse
    {
        $this->authorize('changeStatus', $party);

        $action->handle($this->actor($request), $party);
        Flash::saved($party->code);

        return back();
    }

    /**
     * BR-PTY-09: the party picker's list. Active parties of the given types, searched
     * by name, Urdu name, code and mobile; at most 20; labelled "Name – Area – Mobile".
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Party::class);

        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'types' => ['nullable', 'array'],
            'types.*' => [Rule::enum(PartyType::class)],
        ]);

        $parties = self::search(Party::query()->active(), (string) ($input['q'] ?? ''))
            ->when($input['types'] ?? null, fn (Builder $query, array $types) => $query->whereIn('type', $types))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $parties->map(fn (Party $party): array => [
                'id' => $party->id,
                'code' => $party->code,
                'type' => $party->type->value,
                'label' => self::label($party),
            ])->all(),
        ]);
    }

    /** BR-PTY-09: "Name – Area – Mobile"; the area is left out when empty. */
    public static function label(Party $party): string
    {
        return implode(' – ', array_filter([$party->name, $party->area, $party->mobile], fn (?string $part): bool => $part !== null && $part !== ''));
    }

    /**
     * Name, Urdu name and code contain the text; the mobile contains its digits.
     *
     * @param  Builder<Party>  $query
     * @return Builder<Party>
     */
    private static function search(Builder $query, string $text): Builder
    {
        $text = trim($text);

        if ($text === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
        $digits = preg_replace('/\D/', '', $text) ?? '';

        return $query->where(function (Builder $query) use ($like, $digits): void {
            $query->where('name', 'ilike', $like)
                ->orWhere('name_ur', 'ilike', $like)
                ->orWhere('code', 'ilike', $like);

            if (strlen($digits) >= 3) {
                $query->orWhere('mobile', 'like', '%'.$digits.'%');
            }
        });
    }
}
