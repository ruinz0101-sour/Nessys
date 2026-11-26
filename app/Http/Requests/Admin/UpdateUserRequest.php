<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $targetUser */
        $targetUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'quota_gb' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
