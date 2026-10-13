<?php

namespace App\Http\Requests\Payments;

use App\Domain\Payments\Enums\PaymentDirection;
use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Page 10 field shapes (spec §11.8); the rules are checked in CreatePayment. */
class StorePaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], // BR-GEN-11
            'direction' => ['required', Rule::enum(PaymentDirection::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'max:20'],
            'money_account_id' => ['required', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'handled_by_party_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file'], // checked by StoreAttachment (BR-SEC-06)
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
