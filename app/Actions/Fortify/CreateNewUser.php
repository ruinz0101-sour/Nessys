<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public const int DEFAULT_QUOTA_BYTES = 10 * 1024 * 1024 * 1024; // 10 GB

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['nullable', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique(User::class, 'email'),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $email = Str::lower(trim($input['email']));
        $rawName = isset($input['name']) ? trim($input['name']) : '';
        $name = $rawName !== ''
            ? $rawName
            : Str::limit((string) Str::before($email, '@'), 150, '');

        $user = User::create([
            'name' => $name !== '' ? $name : 'User',
            'email' => $email,
            'password' => Hash::make($input['password']),
            'role' => UserRole::User,
            'department_id' => null,
            'quota_bytes' => self::DEFAULT_QUOTA_BYTES,
            'used_bytes' => 0,
            'is_active' => true,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'register',
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            'meta' => [
                'email' => $user->email,
            ],
            'created_at' => now(),
        ]);

        return $user;
    }
}
