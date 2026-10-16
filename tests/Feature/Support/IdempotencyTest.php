<?php

use App\Models\User;
use App\Support\Idempotency;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** A throwaway model used only by this test file (card T1.13). */
final class IdempotencyProbe extends Model
{
    use BelongsToCompany;

    protected $table = 'idempotency_probes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('idempotency_probes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained('companies');
        $table->string('voucher_no', 20);
        $table->uuid('idempotency_key')->nullable();
        $table->foreignId('created_by')->constrained('users');
        $table->timestampsTz();

        $table->unique(['company_id', 'idempotency_key']);
        $table->unique(['company_id', 'voucher_no']);
    });
});

function probe(User $user, string $key, string $voucher = 'PUR-000001'): IdempotencyProbe
{
    return IdempotencyProbe::query()->create([
        'company_id' => $user->company_id,
        'voucher_no' => $voucher,
        'idempotency_key' => $key,
        'created_by' => $user->id,
    ]);
}

it('BR-GEN-11 an existing key of the same user is found', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();
    $saved = probe($user, $key);

    $found = Idempotency::existing(IdempotencyProbe::class, $user, $key);

    expect($found?->getKey())->toBe($saved->getKey())
        ->and(Idempotency::existing(IdempotencyProbe::class, $user, (string) Str::uuid()))->toBeNull();
});

it('BR-GEN-11 the same key of another user is not returned', function () {
    $key = (string) Str::uuid();
    probe(User::factory()->create(), $key);

    expect(Idempotency::existing(IdempotencyProbe::class, User::factory()->create(), $key))->toBeNull();
});

it('BR-GEN-11 a concurrent duplicate insert is recognised as a duplicate', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();
    probe($user, $key, 'PUR-000001');

    try {
        // The second request of a double tap. A nested DB::transaction() cannot be used
        // here: this file creates its table in beforeEach, and MariaDB has no transactional
        // DDL, so that CREATE TABLE commits the wrapping test transaction and its savepoints
        // are gone. What the helper is asked to recognise is the driver error itself.
        probe($user, $key, 'PUR-000002');
        $this->fail('The duplicate key was accepted.');
    } catch (QueryException $e) {
        expect(Idempotency::isDuplicateKeyViolation($e))->toBeTrue();
    }
});

it('BR-GEN-11 another unique violation is not mistaken for a duplicate submit', function () {
    $user = User::factory()->create();
    probe($user, (string) Str::uuid(), 'PUR-000001');

    try {
        // An idempotency key of its own, so the only clash is the voucher number.
        probe($user, (string) Str::uuid(), 'PUR-000001');
        $this->fail('The duplicate voucher was accepted.');
    } catch (QueryException $e) {
        expect(Idempotency::isDuplicateKeyViolation($e))->toBeFalse();
    }
});

it('BR-GEN-11 the already-saved message names the voucher', function () {
    expect(Idempotency::alreadySavedMessage('PUR-000012'))->toBe('Already saved. PUR-000012');
});
