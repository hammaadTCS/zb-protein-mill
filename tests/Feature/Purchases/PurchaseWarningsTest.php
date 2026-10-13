<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Items\Models\Item;
use App\Domain\Purchases\Actions\AddPurchaseRate;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Models\Purchase;
use App\Models\User;
use App\Support\Warnings\WarningRequired;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => freezeToday('2026-01-31'));

/**
 * @param  array<string, mixed>  $data
 * @param  list<string>  $confirmed
 */
function warnPurchase(User $by, array $data, array $confirmed = []): Purchase
{
    return app(CreatePurchase::class)->handle($by, [
        'idempotency_key' => (string) Str::uuid(),
        'item_id' => Item::query()->where('code', 'WASTE')->value('id'),
        ...$data,
    ], $confirmed);
}

/**
 * @return array{User, array<string, mixed>}
 */
function warningsWorld(): array
{
    $owner = owner();

    return [$owner, acceptanceWorld(acceptance('at1'), $owner)['parties']];
}

it('BR-PUR-05 last rate returns 52.00 for Aslam after AT-1 #10', function () {
    [$owner, $parties] = warningsWorld();
    foreach ([1, 10] as $no) {
        $entry = collect(acceptance('at1')['entries'])->firstWhere('no', $no);
        warnPurchase($owner, ['date' => $entry['date'], 'party_id' => $parties[$entry['party']]->id, 'kg' => $entry['kg'], 'rate' => $entry['rate']]);
    }

    $this->actingAs($owner)
        ->getJson(route('purchases.last-rate', ['party_id' => $parties['aslam']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id')]))
        ->assertOk()
        ->assertExactJson(['rate' => '52.00', 'date' => '2026-01-06']);

    $this->actingAs($owner)
        ->getJson(route('purchases.last-rate', ['party_id' => $parties['karim']->id, 'item_id' => Item::query()->where('code', 'WASTE')->value('id')]))
        ->assertOk()
        ->assertContent('null');
});

it('BR-PUR-06 rate 70 against an average of 50 with limit 30% warns; confirmed it saves and logs', function () {
    [$owner, $parties] = warningsWorld();
    warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50']);
    $data = ['date' => '2026-01-06', 'party_id' => $parties['karim']->id, 'kg' => '100', 'rate' => '70'];

    try {
        warnPurchase($owner, $data);
        $this->fail('No warning.');
    } catch (WarningRequired $warning) {
        expect($warning->warningCode)->toBe('unusual_rate')
            ->and($warning->getMessage())->toBe('This rate is 40% higher than the recent average of Rs 50.00. Check it before saving.');
    }

    $saved = warnPurchase($owner, $data, ['unusual_rate']);

    expect($saved->voucher_no)->toBe('PUR-000002')
        ->and(AuditLog::query()->where('action', 'warning_confirmed')->sole()->new_values['code'])->toBe('unusual_rate');
});

it('BR-PUR-06 rate 60 against 50 does not warn', function () {
    [$owner, $parties] = warningsWorld();
    warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50']);

    expect(warnPurchase($owner, ['date' => '2026-01-06', 'party_id' => $parties['karim']->id, 'kg' => '100', 'rate' => '60'])->rate)->toBe('60.0000');
});

it('BR-PUR-06 a lower rate warns with "lower"', function () {
    [$owner, $parties] = warningsWorld();
    warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50']);

    expect(fn () => warnPurchase($owner, ['date' => '2026-01-06', 'party_id' => $parties['karim']->id, 'kg' => '100', 'rate' => '30']))
        ->toThrow(WarningRequired::class, 'This rate is 40% lower than the recent average of Rs 50.00.');
});

it('BR-PUR-06 weighted average uses the last 5 only', function () {
    [$owner, $parties] = warningsWorld();
    // The oldest purchase (rate 10) must not count; the last five are all at 50.
    warnPurchase($owner, ['date' => '2026-01-01', 'party_id' => $parties['aslam']->id, 'kg' => '10000', 'rate' => '10']);
    foreach (range(2, 6) as $day) {
        warnPurchase($owner, ['date' => sprintf('2026-01-%02d', $day), 'party_id' => $parties['aslam']->id, 'kg' => (string) (100 + $day), 'rate' => '50'], ['unusual_rate']);
    }

    expect(warnPurchase($owner, ['date' => '2026-01-07', 'party_id' => $parties['karim']->id, 'kg' => '100', 'rate' => '64'])->voucher_no)->toBe('PUR-000007')
        ->and(fn () => warnPurchase($owner, ['date' => '2026-01-07', 'party_id' => $parties['karim']->id, 'kg' => '101', 'rate' => '70']))
        ->toThrow(WarningRequired::class, 'recent average of Rs 52.70'); // (6400 + 50 × 418) ÷ 518
});

it('BR-PUR-06 adding a rate checks the unusual rate too', function () {
    [$owner, $parties] = warningsWorld();
    warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50']);
    $pending = warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['karim']->id, 'kg' => '400', 'rate' => null]);

    expect(fn () => app(AddPurchaseRate::class)->handle($owner, $pending, '90', '2026-01-06'))->toThrow(WarningRequired::class, '80% higher');
    expect(app(AddPurchaseRate::class)->handle($owner, $pending, '90', '2026-01-06', ['unusual_rate'])->amount)->toBe('36000.00');
});

it('BR-PUR-08 same party, item, kg and date warns', function () {
    [$owner, $parties] = warningsWorld();
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50'];
    warnPurchase($owner, $data);

    expect(fn () => warnPurchase($owner, $data))
        ->toThrow(WarningRequired::class, 'A purchase with the same party, item, kg and date already exists (PUR-000001).');
    expect(warnPurchase($owner, $data, ['duplicate_entry'])->voucher_no)->toBe('PUR-000002');
});

it('BR-PUR-07 slip number required when the setting is on', function () {
    [$owner, $parties] = warningsWorld();
    DB::table('settings')->where('key', 'slip_no_required')->update(['value' => '1']);
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50'];

    expect(fn () => warnPurchase($owner, $data))->toThrow(ValidationException::class, 'Weight slip number is required.')
        ->and(warnPurchase($owner, [...$data, 'slip_no' => 'WB-1201'])->slip_no)->toBe('WB-1201');
});

it('BR-PUR-07 a slip photo is required when the setting is on', function () {
    Storage::fake('attachments');
    [$owner, $parties] = warningsWorld();
    DB::table('settings')->where('key', 'slip_photo_required')->update(['value' => '1']);
    $data = ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50'];
    $image = imagecreatetruecolor(20, 20);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    expect(fn () => warnPurchase($owner, $data))->toThrow(ValidationException::class, 'Slip photo is required.')
        ->and(warnPurchase($owner, [...$data, 'slip_photo' => UploadedFile::fake()->createWithContent('slip.jpg', $jpeg)])->voucher_no)->toBe('PUR-000001');
});

it('BR-PUR-07 our weight refused when plant has no scale', function () {
    [$owner, $parties] = warningsWorld();
    DB::table('settings')->where('key', 'plant_has_scale')->update(['value' => '0']);

    expect(fn () => warnPurchase($owner, ['date' => '2026-01-05', 'party_id' => $parties['aslam']->id, 'kg' => '800', 'rate' => '50', 'our_weight_kg' => '790']))
        ->toThrow(ValidationException::class, 'Our weight is not used: the plant has no scale.');
});
