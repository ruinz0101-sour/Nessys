<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'quota_gb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
