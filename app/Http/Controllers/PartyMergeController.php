<?php

namespace App\Http\Controllers;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Actions\MergeParties;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyBalance;
use App\Support\Decimal;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 20, Merge parties (D-06, BR-PTY-08): Owner only. */
class PartyMergeController extends Controller
{
    /** Pick source and target; with both picked, show both balances and the result. */
    public function create(Request $request): Response
    {
        Gate::authorize('party.merge');

        // BR-SEC-19: the address bar names both parties by public id (the merge posts ids).
        $input = $request->validate([
            'source_id' => ['nullable', 'string'],
            'target_id' => ['nullable', 'string'],
        ]);
        $sourceId = Party::idFromPublic($input['source_id'] ?? null);
        $targetId = Party::idFromPublic($input['target_id'] ?? null);
        $source = $sourceId === null ? null : Party::query()->find($sourceId);
        $target = $targetId === null ? null : Party::query()->find($targetId);
        $words = fn (PartyBalance $b): array => ['words' => $b->words, 'amount' => $b->amount];
        $result = null;

        if ($source !== null && $target !== null && $source->id !== $target->id && $source->type === $target->type) {
            $nets = [];

            foreach (MergeParties::codes($target) as $code) {
                $sourceNet = LedgerBalance::byCode($code, null, $source->id);
                $targetNet = LedgerBalance::byCode($code, null, $target->id);
                $nets[$code] = Decimal::add($sourceNet, $targetNet);
            }

            $result = $words($target->balanceFrom($nets, '0.000'));
        }

        return Inertia::render('parties/merge', [
            'source' => $source === null ? null : self::summary($source, $words($source->balance())),
            'target' => $target === null ? null : self::summary($target, $words($target->balance())),
            'result' => $result,
        ]);
    }

    public function store(Request $request, MergeParties $action): RedirectResponse
    {
        Gate::authorize('party.merge');

        $input = $request->validate([
            'source_id' => ['required', 'integer'],
            'target_id' => ['required', 'integer'],
        ]);
        $source = Party::query()->findOrFail((int) $input['source_id']);
        $target = Party::query()->findOrFail((int) $input['target_id']);
        $action->handle($this->actor($request), $source, $target);
        Flash::success(__(':source merged into :target.', ['source' => "{$source->name} ({$source->code})", 'target' => "{$target->name} ({$target->code})"]));

        return to_route('parties.show', $target);
    }

    /**
     * @param  array{words: string, amount: string}  $balance
     * @return array<string, mixed>
     */
    private static function summary(Party $party, array $balance): array
    {
        return [
            'id' => $party->id,
            'public_id' => $party->public_id,
            'code' => $party->code,
            'type' => $party->type->value,
            'label' => PartyController::label($party),
            'status' => $party->status->value,
            'balance' => $balance,
        ];
    }
}
