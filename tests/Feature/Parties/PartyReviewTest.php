<?php

use App\Domain\Alerts\Alerts;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Parties\Actions\CreateParty;
use App\Domain\Parties\Actions\ReviewParty;
use App\Domain\Parties\Actions\UpdateParty;
use App\Domain\Parties\Models\Party;
use Inertia\Testing\AssertableInertia as Assert;

/** A supplier the accountant added, then whose bank details they changed. */
function accountantSupplier(): Party
{
    $party = app(CreateParty::class)->handle(accountant(), [
        'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001', 'bank_name' => 'HBL', 'bank_account_number' => '1111',
    ]);
    app(UpdateParty::class)->handle(accountant(), $party, [
        'type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001', 'bank_name' => 'MCB', 'bank_account_number' => '2222',
        'version' => $party->refresh()->updated_at?->toIso8601String(),
    ]);

    return $party->refresh();
}

function alertCount(string $key, $user): int
{
    return collect(Alerts::for($user))->firstWhere('key', $key)['count'] ?? 0;
}

it('BR-PTY-06 BR-PTY-07 owner can mark reviewed; accountant gets 403', function () {
    $party = accountantSupplier();
    $owner = owner();

    expect($party->needs_review)->toBeTrue()->and($party->bank_change_needs_review)->toBeTrue();

    $this->actingAs(accountant())->post(route('parties.review', $party), ['what' => 'new_party'])->assertForbidden();
    $this->actingAs($owner)->post(route('parties.review', $party), ['what' => 'new_party'])->assertRedirect();
    $this->actingAs($owner)->post(route('parties.review', $party), ['what' => 'bank_change'])->assertRedirect();

    $party->refresh();

    expect($party->needs_review)->toBeFalse()
        ->and($party->bank_change_needs_review)->toBeFalse()
        ->and($party->reviewed_by)->toBe($owner->id)
        ->and($party->reviewed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'reviewed')->where('auditable_id', $party->id)->count())->toBe(2);
});

it('alert count drops after review', function () {
    $party = accountantSupplier();
    accountantSupplierTwo();
    $owner = owner();

    expect(alertCount('new_parties_to_review', $owner))->toBe(2)
        ->and(alertCount('bank_changes_to_review', $owner))->toBe(1);

    app(ReviewParty::class)->handle($owner, $party, ReviewParty::NEW_PARTY);
    app(ReviewParty::class)->handle($owner, $party, ReviewParty::BANK_CHANGE);

    expect(alertCount('new_parties_to_review', $owner))->toBe(1)
        ->and(alertCount('bank_changes_to_review', $owner))->toBe(0)
        ->and(collect(Alerts::for($owner))->pluck('key')->all())->toBe(['new_parties_to_review']);
});

it('the accountant sees no review alerts', function () {
    accountantSupplier();

    expect(Alerts::for(accountant()))->toBe([]);
});

it('the party profile shows the review box with the bank change old and new', function () {
    $party = accountantSupplier();

    $this->actingAs(owner())->get(route('parties.show', $party))
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.new_party', true)
            ->where('review.can_review', true)
            ->where('review.bank_change.old.bank_name', 'HBL')
            ->where('review.bank_change.new.bank_name', 'MCB'));

    $this->actingAs(owner())->get(route('parties.index', ['review' => 'bank']))
        ->assertInertia(fn (Assert $page) => $page->where('parties.total', 1));
});

function accountantSupplierTwo(): Party
{
    return app(CreateParty::class)->handle(accountant(), ['type' => 'supplier', 'name' => 'Karim', 'mobile' => '03000000002']);
}
