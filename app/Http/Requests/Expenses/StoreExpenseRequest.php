<?php

namespace App\Http\Requests\Expenses;

use App\Domain\Expenses\Enums\ExpensePaymentMode;
use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Page 13 field shapes (spec §11.9); the rules are checked in CreateExpense. */
class StoreExpenseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], // BR-GEN-11
            'date' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'max:20'],
            'payment_mode' => ['required', Rule::enum(ExpensePaymentMode::class)],
            'money_account_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'integer'],
            'handled_by_party_id' => ['nullable', 'integer'],
            'linked_purchase_id' => ['nullable', 'integer'],
            'linked_sale_id' => ['nullable', 'integer'],
            'note' => ['present', 'nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file'], // checked by StoreAttachment (BR-SEC-06)
            'add_another' => ['sometimes', 'boolean'],
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
