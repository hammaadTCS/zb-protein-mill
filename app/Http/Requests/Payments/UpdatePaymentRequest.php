<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;

/** Edit of a payment: the same fields without the one-time key, plus `version` (BR-GEN-09). */
class UpdatePaymentRequest extends StorePaymentRequest
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
