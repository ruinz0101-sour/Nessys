<?php

namespace App\Models;

use App\Enums\SharePermission;
use Database\Factories\ShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'node_id',
    'shared_by',
    'shared_with_user_id',
    'shared_with_department_id',
    'permission',
    'expires_at',
    'revoked_at',
])]
class Share extends Model
{
    /** @use HasFactory<ShareFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function sharedWithDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'shared_with_department_id');
    }
}
