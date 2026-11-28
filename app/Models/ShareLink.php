<?php

namespace App\Models;

use App\Enums\SharePermission;
use Database\Factories\ShareLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'node_id',
    'created_by',
    'token',
    'password_hash',
    'permission',
    'max_downloads',
    'download_count',
    'expires_at',
    'revoked_at',
    'created_at',
])]
class ShareLink extends Model
{
    /** @use HasFactory<ShareLinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isDownloadLimitReached(): bool
    {
        return $this->max_downloads !== null && $this->download_count >= $this->max_downloads;
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isDownloadLimitReached();
    }

    public function hasPassword(): bool
    {
        return $this->password_hash !== null && $this->password_hash !== '';
    }
}
