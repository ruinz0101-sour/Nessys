<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'user_id',
    'action',
    'node_id',
    'node_name',
    'ip',
    'user_agent',
    'meta',
    'created_at',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are append-only (INSERT/SELECT only) and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are append-only (INSERT/SELECT only) and cannot be deleted.');
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }
}
