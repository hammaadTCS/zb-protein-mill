<?php

namespace App\Http\Requests\Parties;

use App\Domain\Parties\Enums\PartyType;
use App\Rules\DecimalString;
use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Spec §9.2 parties, BR-PTY-10. Mobile is stored as digits only (10 to 15).
 * There is no CNIC field (F-22).
 */
class StorePartyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PartyType::class)],
            'name' => ['required', 'string', 'max:150'],
            'name_ur' => ['nullable', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:/^\d{10,15}$/'],
            'area' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_title' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:150'],
            // BR-PTY-10: buyers only.
            'credit_limit' => ['nullable', 'prohibited_unless:type,buyer', DecimalString::money()],
            'payment_terms_days' => ['nullable', 'prohibited_unless:type,buyer', 'integer', 'between:0,365'],
            // BR-PTY-10: partners only.
            'profit_share_percent' => ['nullable', 'prohibited_unless:type,partner', new DecimalString(2, '0', '100')],
            'joining_date' => ['nullable', 'prohibited_unless:type,partner', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirmed_warnings' => ['sometimes', 'array'],
            'confirmed_warnings.*' => ['string', Rule::in(Warnings::CODES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex' => __('Mobile must be 10 to 15 digits.'),
            '*.prohibited_unless' => __('This field is only for this party type.'),
        ];
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

    /**
     * The validated party fields, without confirmed_warnings.
     *
     * @return array<string, mixed>
     */
    public function partyData(): array
    {
        return $this->safe()->except('confirmed_warnings');
    }

    protected function prepareForValidation(): void
    {
        // Spaces and dashes are allowed while typing; only digits are stored.
        if (is_string($this->input('mobile'))) {
            $this->merge(['mobile' => str_replace([' ', '-'], '', $this->input('mobile'))]);
        }
    }
}
