<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'avatar_path',
    'password',
    'role',
    'department_id',
    'quota_bytes',
    'used_bytes',
    'is_active',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'two_factor_confirmed_at',
    'failed_login_count',
    'locked_until',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'quota_bytes' => 'integer',
            'used_bytes' => 'integer',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function managedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'manager_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'owner_id');
    }

    public function sharedByMe(): HasMany
    {
        return $this->hasMany(Share::class, 'shared_by');
    }

    public function sharedWithMe(): HasMany
    {
        return $this->hasMany(Share::class, 'shared_with_user_id');
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class, 'requester_id');
    }

    public function receivedAccessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class, 'owner_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }

    public function avatarUrl(): ?string
    {
        if (empty($this->avatar_path)) {
            return null;
        }

        return route('users.avatar', [
            'user' => $this->id,
            'v' => $this->updated_at?->timestamp ?? 1,
        ]);
    }
}
