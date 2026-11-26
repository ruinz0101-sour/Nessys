<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'manager_id',
])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'department_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class, 'shared_with_department_id');
    }
}
