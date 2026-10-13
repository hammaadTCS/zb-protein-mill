<?php

namespace App\Http\Requests\Expenses;

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Page 44: name (unique per company, spec §9.2), quick button, sort order. */
class ExpenseCategoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $category = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where('company_id', $user instanceof User ? $user->company_id : 0)
                    ->ignore($category instanceof ExpenseCategory ? $category->id : null),
            ],
            'is_quick' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,9999'],
        ];
    }
}
