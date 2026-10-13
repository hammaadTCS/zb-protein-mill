<?php

namespace App\Http\Requests\MoneyAccounts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMoneyAccountRequest extends FormRequest
{
    /**
     * The type is not editable.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            ...StoreMoneyAccountRequest::bankRules(),
        ];
    }
}
