<?php

namespace App\Http\Requests\Transfers;

use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Page 16 field shapes (spec §11.10); the rules are checked in CreateTransfer. */
class StoreTransferRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], // BR-GEN-11
            'date' => ['required', 'date_format:Y-m-d'],
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer'],
            'amount' => ['required', 'max:20'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'confirmed_warnings' => ['sometimes', 'array'],
            'confirmed_warnings.*' => ['string', Rule::in(Warnings::CODES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entryData(): array
    {
        return $this->safe()->except(['confirmed_warnings']);
    }

    /**
     * @return list<string>
     */
    public function confirmedWarnings(): array
    {
        /** @var list<string> $codes */
        $codes = array_values((array) $this->validated('confirmed_warnings', []));

        return $codes;
    }
}
