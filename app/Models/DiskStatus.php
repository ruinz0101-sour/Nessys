<?php

namespace App\Models;

use App\Enums\SmartStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pool_id',
    'device',
    'model',
    'serial',
    'size_bytes',
    'used_bytes',
    'temperature_c',
    'power_on_hours',
    'reallocated_sectors',
    'smart',
    'checked_at',
])]
class DiskStatus extends Model
{
    protected $table = 'disk_status';

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'used_bytes' => 'integer',
            'temperature_c' => 'integer',
            'power_on_hours' => 'integer',
            'reallocated_sectors' => 'integer',
            'smart' => SmartStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(RaidPool::class, 'pool_id');
    }
}
