<?php

use App\Domain\Companies\Models\Company;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A throwaway model used only by this test file (card T1.1). */
final class TenancyProbe extends Model
{
    use BelongsToCompany;

    protected $table = 'tenancy_probes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('tenancy_probes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained('companies');
        $table->string('label');
        $table->timestampsTz();
    });
});

it('BR-GEN-01 records of another company are invisible', function () {
    $mill = Company::query()->orderBy('id')->firstOrFail();
    $other = Company::query()->create(['name' => 'Other Mill']);

    TenancyProbe::query()->create(['company_id' => $mill->id, 'label' => 'mill row']);
    TenancyProbe::query()->create(['company_id' => $other->id, 'label' => 'other row']);

    $this->actingAs(User::factory()->create(['company_id' => $mill->id]));

    expect(TenancyProbe::query()->pluck('label')->all())->toBe(['mill row']);
});

it('BR-GEN-01 company_id is filled from the logged-in user on create', function () {
    $other = Company::query()->create(['name' => 'Other Mill']);
    $this->actingAs(User::factory()->create(['company_id' => $other->id]));

    $probe = TenancyProbe::query()->create(['label' => 'mine']);

    expect($probe->company_id)->toBe($other->id);
});

it('BR-GEN-01 a logged-in user cannot create a record for another company', function () {
    $other = Company::query()->create(['name' => 'Other Mill']);
    $this->actingAs(User::factory()->create());

    TenancyProbe::query()->create(['company_id' => $other->id, 'label' => 'not mine']);
})->throws(LogicException::class);
