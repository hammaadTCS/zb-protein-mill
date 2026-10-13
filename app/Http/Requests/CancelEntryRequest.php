<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** "Cancel entry" with a reason (BR-GEN-04); the Action checks the 5-character minimum. */
class CancelEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'cancel_linked_payment' => ['sometimes', 'boolean'],
        ];
    }
}
