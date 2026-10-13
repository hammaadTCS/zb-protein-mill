<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Ledger\PostingLine;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingService;
use App\Domain\Parties\Actions\ActivateParty;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\DeactivateParty;
use App\Domain\Parties\Actions\UpdateParty;
use App\Domain\Parties\Models\Party;
use App\Http\Requests\Parties\StorePartyRequest;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $fields
 * @param  list<string>  $confirmed
 */
function party(User $by, string $type, string $name, string $mobile, array $fields = [], array $confirmed = []): Party
{
    return app(CreateParty::class)->handle($by, ['type' => $type, 'name' => $name, 'mobile' => $mobile, ...$fields], $confirmed);
}

/** Posts one journal through PostingService (test set-up for party balances). */
function postFor(Party $party, string $debitCode, string $creditCode, string $amount, User $by): void
{
    $partyOn = fn (string $code) => in_array($code, ['1200', '1300', '2000', '2100', '3000', '3100'], true) ? $party->id : null;

    DB::transaction(fn () => PostingService::post(new PostingRequest(
        postingDate: '2026-01-05',
        sourceType: 'party',
        sourceId: $party->id,
        description: 'Test',
        lines: [
            PostingLine::debit($debitCode, $amount, $partyOn($debitCode)),
            PostingLine::credit($creditCode, $amount, $partyOn($creditCode)),
        ],
    ), $by));
}

beforeEach(fn () => freezeToday('2026-01-15'));

it('party codes per type: first supplier S-0001, first buyer B-0001', function () {
    $owner = owner();

    expect(party($owner, 'supplier', 'Aslam', '03000000001')->code)->toBe('S-0001')
        ->and(party($owner, 'supplier', 'Karim', '03000000002')->code)->toBe('S-0002')
        ->and(party($owner, 'buyer', 'ABC Feeds', '03000000004')->code)->toBe('B-0001')
        ->and(party($owner, 'partner', 'Ali', '03000000006')->code)->toBe('P-0001')
        ->and(party($owner, 'employee', 'Nadeem', '03000000010')->code)->toBe('E-0001')
        ->and(party($owner, 'other', 'Test Transporter', '03000000009')->code)->toBe('O-0001');
});

it('BR-PTY-03 same mobile raises duplicate_party; confirmed it saves and logs warning_confirmed', function () {
    $owner = owner();
    party($owner, 'supplier', 'Aslam', '03000000001');

    try {
        party($owner, 'buyer', 'Aslam Feeds', '03000000001');
        $this->fail('No warning was raised.');
    } catch (WarningRequired $warning) {
        expect($warning->warningCode)->toBe('duplicate_party')
            ->and($warning->getMessage())->toBe('A party with this mobile already exists: Aslam (S-0001).');
    }

    expect(Party::query()->count())->toBe(1);

    $saved = party($owner, 'buyer', 'Aslam Feeds', '03000000001', [], ['duplicate_party']);
    $audit = AuditLog::query()->where('action', 'warning_confirmed')->sole();

    expect($saved->code)->toBe('B-0001')
        ->and($audit->auditable_id)->toBe($saved->id)
        ->and($audit->new_values['code'])->toBe('duplicate_party')
        ->and($audit->new_values['details']['code'])->toBe('S-0001');
});

it('BR-PTY-03 the same name ignoring case and spaces raises duplicate_party; inactive parties do not', function () {
    $owner = owner();
    $aslam = party($owner, 'supplier', 'Aslam Bhai', '03000000001');

    expect(fn () => party($owner, 'supplier', 'aslambhai', '03000000011'))
        ->toThrow(WarningRequired::class, 'A party with this name already exists: Aslam Bhai (S-0001).');

    app(DeactivateParty::class)->handle($owner, $aslam);

    expect(party($owner, 'supplier', 'ASLAM BHAI', '03000000001')->code)->toBe('S-0002');
});

it('BR-PTY-03 a duplicate warning renders as 422 JSON with code, message and details', function () {
    $response = (new WarningRequired('duplicate_party', 'A party with this mobile already exists: Aslam (S-0001).', ['code' => 'S-0001']))->render();

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe(['warning' => [
            'code' => 'duplicate_party',
            'message' => 'A party with this mobile already exists: Aslam (S-0001).',
            'details' => ['code' => 'S-0001'],
        ]]);
});

it('BR-PTY-04 type cannot change after the first entry', function () {
    $owner = owner();
    $supplier = party($owner, 'supplier', 'Aslam', '03000000001');

    $changed = app(UpdateParty::class)->handle($owner, $supplier, ['type' => 'other', 'name' => 'Aslam', 'mobile' => '03000000001']);
    expect($changed->type->value)->toBe('other');

    postFor($changed, '5000', '2100', '1000.00', $owner);

    expect(fn () => app(UpdateParty::class)->handle($owner, $changed, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']))
        ->toThrow(ValidationException::class, 'Type cannot change after the first entry.');
});

it('BR-PTY-05 there is no delete route', function () {
    $owner = owner();
    $party = party($owner, 'supplier', 'Aslam', '03000000001');

    $status = $this->actingAs($owner)->delete('/parties/'.$party->id)->status();

    expect($status)->toBeIn([404, 405])
        ->and(Party::query()->whereKey($party->id)->exists())->toBeTrue();
    expect(fn () => $party->delete())->toThrow(LogicException::class);
});

it('BR-PTY-05 a party is made inactive and active again', function () {
    $owner = owner();
    $party = party($owner, 'supplier', 'Aslam', '03000000001');

    app(DeactivateParty::class)->handle($owner, $party);
    expect(Party::query()->active()->count())->toBe(0);

    app(ActivateParty::class)->handle($owner, $party);
    expect($party->refresh()->status->value)->toBe('active');
});

it('BR-PTY-06 a party created by the accountant needs review; by the owner it does not', function () {
    expect(party(accountant(), 'supplier', 'Aslam', '03000000001')->needs_review)->toBeTrue()
        ->and(party(owner(), 'supplier', 'Karim', '03000000002')->needs_review)->toBeFalse();
});

it('BR-PTY-07 accountant bank change sets review flag and logs old and new values', function () {
    $accountant = accountant();
    $party = party(owner(), 'supplier', 'Aslam', '03000000001', ['bank_name' => 'HBL', 'bank_account_number' => '111']);

    app(UpdateParty::class)->handle($accountant, $party, [
        'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001',
        'bank_name' => 'HBL', 'bank_account_number' => '222',
    ]);

    $log = AuditLog::query()->where('action', 'bank_details_changed')->sole();

    expect($party->refresh()->bank_change_needs_review)->toBeTrue()
        ->and($log->user_id)->toBe($accountant->id)
        ->and($log->old_values)->toBe(['bank_name' => 'HBL', 'bank_account_title' => null, 'bank_account_number' => '111'])
        ->and($log->new_values)->toBe(['bank_name' => 'HBL', 'bank_account_title' => null, 'bank_account_number' => '222']);
});

it('BR-PTY-07 an owner bank change needs no review', function () {
    $owner = owner();
    $party = party($owner, 'supplier', 'Aslam', '03000000001', ['bank_account_number' => '111']);

    app(UpdateParty::class)->handle($owner, $party, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001', 'bank_account_number' => '222']);

    expect($party->refresh()->bank_change_needs_review)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'bank_details_changed')->count())->toBe(0);
});

it('BR-PTY-10 credit limit on a supplier is refused', function () {
    $rules = (new StorePartyRequest)->rules();

    $supplier = Validator::make(['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001', 'credit_limit' => '50000'], $rules);
    $buyer = Validator::make(['type' => 'buyer', 'name' => 'ABC', 'mobile' => '03000000004', 'credit_limit' => '50000', 'payment_terms_days' => 30], $rules);
    $partnerShare = Validator::make(['type' => 'buyer', 'name' => 'ABC', 'mobile' => '03000000004', 'profit_share_percent' => '50'], $rules);

    expect($supplier->errors()->keys())->toBe(['credit_limit'])
        ->and($buyer->passes())->toBeTrue()
        ->and($partnerShare->errors()->keys())->toBe(['profit_share_percent']);
});

it('BR-PTY-10 a type change clears the fields of the old type', function () {
    $owner = owner();
    $buyer = party($owner, 'buyer', 'ABC', '03000000004', ['credit_limit' => '50000.00', 'payment_terms_days' => 30]);

    $other = app(UpdateParty::class)->handle($owner, $buyer, ['type' => 'other', 'name' => 'ABC', 'mobile' => '03000000004', 'credit_limit' => '50000.00']);

    expect($other->credit_limit)->toBeNull()->and($other->payment_terms_days)->toBeNull();
});

it('mobile is required, 10 to 15 digits; spaces and dashes are removed', function () {
    $owner = owner();
    $request = StorePartyRequest::create('/parties', 'POST', ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '0300-000 0001']);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    expect($request->partyData()['mobile'])->toBe('03000000001');

    $short = Validator::make(['type' => 'supplier', 'name' => 'A', 'mobile' => '12345'], (new StorePartyRequest)->rules());
    expect($short->errors()->keys())->toBe(['mobile']);
});

it('BR-LED-04 posting a buyer on account 2000 is refused', function () {
    $owner = owner();
    $buyer = party($owner, 'buyer', 'ABC Feeds', '03000000004');

    expect(fn () => postFor($buyer, '5000', '2000', '100.00', $owner))
        ->toThrow(InvalidArgumentException::class, 'Account 2000 needs a supplier party (BR-LED-04).');
});

it('balance words', function () {
    $owner = owner();
    $supplier = party($owner, 'supplier', 'Aslam', '03000000001');
    $advanced = party($owner, 'supplier', 'Bashir', '03000000003');
    $buyer = party($owner, 'buyer', 'ABC Feeds', '03000000004');
    $buyerAdvance = party($owner, 'buyer', 'Rehman Feeds', '03000000005');
    $employee = party($owner, 'employee', 'Nadeem', '03000000010');
    $settled = party($owner, 'supplier', 'Karim', '03000000002');

    postFor($supplier, '5000', '2000', '52000.00', $owner);
    postFor($advanced, '2000', '3900', '20000.00', $owner);
    postFor($buyer, '1200', '4000', '23000.00', $owner);
    postFor($buyerAdvance, '3900', '1200', '30000.00', $owner);
    postFor($employee, '1300', '3900', '5000.00', $owner);

    expect(partyBalance($supplier))->toBe(['words' => 'We owe', 'amount' => '52000.00'])
        ->and(partyBalance($advanced))->toBe(['words' => 'Advance', 'amount' => '20000.00'])
        ->and(partyBalance($buyer))->toBe(['words' => 'Owes us', 'amount' => '23000.00'])
        ->and(partyBalance($buyerAdvance))->toBe(['words' => 'Advance', 'amount' => '30000.00'])
        ->and(partyBalance($employee))->toBe(['words' => 'Owes us', 'amount' => '5000.00'])
        ->and(partyBalance($settled))->toBe(['words' => 'Settled', 'amount' => '0.00'])
        ->and($supplier->balance()->pendingKg)->toBe('0.000')
        ->and(ledgerBalance('2000', $supplier))->toBe('-52000.00');
});

it('R-09 a partner balance has invested, withdrawn and net', function () {
    $owner = owner();
    $usman = party($owner, 'partner', 'Usman', '03000000007');

    postFor($usman, '3900', '3000', '180000.00', $owner);
    postFor($usman, '3100', '3900', '10000.00', $owner);

    $balance = $usman->balance();

    expect([$balance->invested, $balance->withdrawn, $balance->net])->toBe(['180000.00', '10000.00', '170000.00'])
        ->and([$balance->words, $balance->amount])->toBe(['Net invested', '170000.00']);
});
