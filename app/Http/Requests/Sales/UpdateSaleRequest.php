<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Edit of a sale (BR-PUR-11 for sales): the same fields as a new one without
 * "Received now" and the one-time key, plus `version` for BR-GEN-09.
 */
class UpdateSaleRequest extends StoreSaleRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key'], $rules['received_now'], $rules['received_now.amount'], $rules['received_now.money_account_id'], $rules['received_now.reference_no'], $rules['received_now.handled_by_party_id'], $rules['slip_photo'], $rules['add_another']);
        $rules['version'] = ['required', 'string', 'max:40'];

        return $rules;
    }
}
