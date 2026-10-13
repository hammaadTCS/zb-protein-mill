<?php

namespace App\Http\Requests\Partners;

use Illuminate\Contracts\Validation\ValidationRule;

/** Edit of a partner entry: same fields without the one-time key, plus `version` (BR-GEN-09). */
class UpdatePartnerTransactionRequest extends StorePartnerTransactionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key']);
        $rules['version'] = ['required', 'string', 'max:40'];

        return $rules;
    }
}
