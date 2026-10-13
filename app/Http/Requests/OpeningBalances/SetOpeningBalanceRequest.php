<?php

namespace App\Http\Requests\OpeningBalances;

use App\Domain\OpeningBalances\Enums\OpeningSide;
use App\Rules\DecimalString;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One opening: a money account or a party (exactly one), amount ≥ 0, side for parties. */
class SetOpeningBalanceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'money_account_id' => ['nullable', 'integer', 'required_without:party_id', 'prohibits:party_id'],
            'party_id' => ['nullable', 'integer', 'required_without:money_account_id'],
            'amount' => ['required', DecimalString::money()],
            'side' => ['nullable', Rule::enum(OpeningSide::class)],
        ];
    }
}
