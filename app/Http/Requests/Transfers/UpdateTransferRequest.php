<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;

/** Edit of a transfer: same fields without the one-time key, plus `version` (BR-GEN-09). */
class UpdateTransferRequest extends StoreTransferRequest
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
