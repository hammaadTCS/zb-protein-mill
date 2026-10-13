<?php

namespace App\Http\Requests\Adjustments;

use App\Domain\Adjustments\Enums\AdjustmentReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Spec §11.7 field shapes; the rules are checked in CreateAdjustment. */
class StoreAdjustmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'], // BR-GEN-11
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'max:20'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
