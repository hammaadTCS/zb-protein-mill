<?php

namespace App\Http\Requests\Purchases;

use App\Support\Warnings\Warnings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Add rate" on a rate-pending purchase or sale (BR-PUR-04): the rate and the day it is added. */
class AddRateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate' => ['required', 'max:20'],
            'date' => ['required', 'date_format:Y-m-d'],
            'confirmed_warnings' => ['sometimes', 'array'],
            'confirmed_warnings.*' => ['string', Rule::in(Warnings::CODES)],
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
}
