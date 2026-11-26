<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\SharePermission;
use Database\Factories\AccessRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'node_id',
    'requester_id',
    'owner_id',
    'reason',
    'status',
    'decided_by',
    'decided_at',
    'decision_note',
    'granted_permission',
    'granted_until',
    'last_reminded_at',
])]
class AccessRequest extends Model
{
    /** @use HasFactory<AccessRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'granted_permission' => SharePermission::class,
            'decided_at' => 'datetime',
            'granted_until' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
