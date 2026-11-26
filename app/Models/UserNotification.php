<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'id',
    'user_id',
    'type',
    'data',
    'read_at',
    'created_at',
])]
class UserNotification extends Model
{
    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (UserNotification $notification): void {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid();
            }

            if ($notification->created_at === null) {
                $notification->created_at = now();
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
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
