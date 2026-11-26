<?php

namespace App\Models;

use App\Enums\RaidState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'level',
    'state',
    'total_bytes',
    'used_bytes',
    'checked_at',
])]
class RaidPool extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => RaidState::class,
            'total_bytes' => 'integer',
            'used_bytes' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function disks(): HasMany
    {
        return $this->hasMany(DiskStatus::class, 'pool_id');
    }
}
