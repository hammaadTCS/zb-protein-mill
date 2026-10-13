<?php

namespace App\Http\Controllers;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Actions\MarkOpeningBalancesComplete;
use App\Domain\OpeningBalances\Actions\SetOpeningBalance;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Parties\Models\Party;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Requests\OpeningBalances\SetOpeningBalanceRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page 47, Opening balances (/settings/opening-balances). */
class OpeningBalanceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', OpeningBalance::class);

        $openings = OpeningBalance::query()->get();
        $byAccount = $openings->whereNotNull('money_account_id')->keyBy('money_account_id');
        $byParty = $openings->whereNotNull('party_id')->keyBy('party_id');

        $opening = fn (?OpeningBalance $row): ?array => $row === null ? null : [
            'voucher_no' => $row->voucher_no,
            'amount' => $row->amount,
            'side' => $row->side->value,
        ];

        return Inertia::render('opening-balances/index', [
            'start_date' => Settings::get(SettingKeys::SYSTEM_START_DATE),
            'completed' => OpeningBalances::completed(),
            'locked_by' => OpeningBalances::lockedBy(), // BR-MEC-09
            'equity' => OpeningBalances::equity(),
            'money_accounts' => MoneyAccount::query()->active()->orderBy('type')->orderBy('name')->get()
                ->map(fn (MoneyAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'opening' => $opening($byAccount->get($account->id)),
                ]),
            'parties' => Party::query()->active()->orderBy('type')->orderBy('name')->get()
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'code' => $party->code,
                    'name' => $party->name,
                    'type' => $party->type->value,
                    'opening' => $opening($byParty->get($party->id)),
                ]),
        ]);
    }

    public function store(SetOpeningBalanceRequest $request, SetOpeningBalance $action): RedirectResponse
    {
        $this->authorize('manage', OpeningBalance::class);

        /** @var array{money_account_id?: int|null, party_id?: int|null, amount: string, side?: string|null} $data */
        $data = $request->validated();
        $opening = $action->handle($this->actor($request), $data);

        $opening !== null ? Flash::saved($opening->voucher_no) : Flash::success(__('Saved.'));

        return back();
    }

    public function complete(Request $request, MarkOpeningBalancesComplete $action): RedirectResponse
    {
        $this->authorize('manage', OpeningBalance::class);

        $input = $request->validate(['confirmed_obe_amount' => ['required', 'string', 'max:20']]);
        $action->handle($this->actor($request), (string) $input['confirmed_obe_amount']);

        Flash::success(__('Saved.'));

        return back();
    }
}
