<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dorm_id', 'code', 'name', 'is_active'])]
class Building extends Model
{
    protected function casts(): array
    {
        return [
            'dorm_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }
}
