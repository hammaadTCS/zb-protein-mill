<?php

namespace App\Http\Requests\Items;

use App\Domain\Items\Enums\ItemType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof User ? $user->company_id : 0;

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/', Rule::unique('items', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:100', Rule::unique('items', 'name')->where('company_id', $companyId)],
            'type' => ['required', Rule::enum(ItemType::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
