<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Page 42: key => value; each value is checked by Settings::normalize() for its key. */
class UpdateCompanySettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
            'values.*' => ['nullable'],
        ];
    }
}
