<?php

namespace App\Http\Requests\Items;

use App\Domain\Items\Enums\ItemType;
use App\Domain\Items\Models\Item;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The code never changes. */
class UpdateItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $item = $this->route('item');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('items', 'name')
                    ->where('company_id', $user instanceof User ? $user->company_id : 0)
                    ->ignore($item instanceof Item ? $item->id : null),
            ],
            'type' => ['required', Rule::enum(ItemType::class)],
        ];
    }
}
