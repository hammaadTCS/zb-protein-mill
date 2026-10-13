<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Page 43, add user: name, e-mail, role and a temporary password (BR-SEC-02). */
class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::in([RolesAndPermissionsSeeder::OWNER, RolesAndPermissionsSeeder::ACCOUNTANT])],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
