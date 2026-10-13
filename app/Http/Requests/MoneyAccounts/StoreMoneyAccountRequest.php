<?php

namespace App\Http\Requests\MoneyAccounts;

use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyAccountRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(MoneyAccountType::class)],
            ...self::bankRules(),
        ];
    }

    /**
     * Bank details are optional; they are kept only for bank accounts.
     *
     * @return array<string, array<int, string>>
     */
    public static function bankRules(): array
    {
        return [
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_title' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:150'],
        ];
    }
}
