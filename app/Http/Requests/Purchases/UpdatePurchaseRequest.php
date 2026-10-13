<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Edit of a purchase (BR-PUR-11): the same fields as a new one without "Paid now"
 * and the one-time key, plus `version` for BR-GEN-09.
 */
class UpdatePurchaseRequest extends StorePurchaseRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key'], $rules['paid_now'], $rules['paid_now.amount'], $rules['paid_now.money_account_id'], $rules['paid_now.reference_no'], $rules['paid_now.handled_by_party_id'], $rules['slip_photo'], $rules['add_another']);
        $rules['version'] = ['required', 'string', 'max:40'];

        return $rules;
    }
}
