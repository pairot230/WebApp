<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['floor_id', 'number', 'capacity', 'is_active'])]
class Room extends Model
{
    public const CAPACITY = 2;

    protected $attributes = ['capacity' => self::CAPACITY, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'floor_id' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function repairRequests(): HasMany
    {
        return $this->hasMany(RepairRequest::class);
    }
}
