<?php

namespace App\Http\Requests\Purchases;

use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Page 4 field shapes (spec §11.4). The business rules (BR-PUR-01 to BR-PUR-03,
 * BR-PUR-10) are checked in CreatePurchase. An `amount` sent by the browser is not
 * a field and is ignored (BR-GEN-06).
 */
class StorePurchaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'party_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'kg' => ['required', 'max:20'],
            'rate' => ['nullable', 'max:20'],
            'slip_no' => ['nullable', 'string', 'max:50'],
            'our_weight_kg' => ['nullable', 'max:20'],
            'note' => ['nullable', 'string', 'max:2000'],
            'slip_photo' => ['nullable', 'file'], // type and size checked by StoreAttachment (BR-SEC-06)
            'add_another' => ['sometimes', 'boolean'],
            'idempotency_key' => ['required', 'uuid'], // BR-GEN-11
            'paid_now' => ['nullable', 'array'],
            'paid_now.amount' => ['required_with:paid_now', 'max:20'],
            'paid_now.money_account_id' => ['required_with:paid_now', 'integer'],
            'paid_now.reference_no' => ['nullable', 'string', 'max:100'],
            'paid_now.handled_by_party_id' => ['nullable', 'integer'],
            'confirmed_warnings' => ['sometimes', 'array'],
            'confirmed_warnings.*' => ['string', Rule::in(Warnings::CODES)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entryData(): array
    {
        return $this->safe()->except(['confirmed_warnings', 'add_another']);
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
