<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Contracts\Validation\ValidationRule;

/** Edit of an expense: same fields without the one-time key, plus `version` (BR-GEN-09). */
class UpdateExpenseRequest extends StoreExpenseRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key'], $rules['photo']);
        $rules['version'] = ['required', 'string', 'max:40'];

        return $rules;
    }
}
