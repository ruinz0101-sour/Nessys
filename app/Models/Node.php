<?php

namespace App\Models;

use App\Enums\FileCategory;
use App\Enums\NodeType;
use App\Enums\RootType;
use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'parent_id',
    'type',
    'name',
    'root_type',
    'department_id',
    'owner_id',
    'storage_path',
    'size_bytes',
    'mime_type',
    'extension',
    'category',
    'sha256',
    'is_important',
    'is_dropzone',
    'deleted_at',
    'deleted_by',
    'purge_at',
])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Node $node): void {
            if (empty($node->uuid)) {
                $node->uuid = (string) Str::uuid();
            }
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
            'type' => NodeType::class,
            'root_type' => RootType::class,
            'category' => FileCategory::class,
            'size_bytes' => 'integer',
            'is_important' => 'boolean',
            'is_dropzone' => 'boolean',
            'deleted_at' => 'datetime',
            'purge_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Node::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class, 'node_id');
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class, 'node_id');
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class, 'node_id');
    }
}
