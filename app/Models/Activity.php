<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dorm_id', 'academic_year_id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'score', 'status', 'created_by'])]
class Activity extends Model
{
    public const STATUSES = ['draft', 'open', 'closed', 'cancelled'];

    public const STATUS_LABELS = ['draft' => 'ฉบับร่าง', 'open' => 'เปิดเช็กชื่อ', 'closed' => 'ปิดเช็กชื่อ', 'cancelled' => 'ยกเลิก'];

    public const AUDIT_FIELDS = ['dorm_id', 'academic_year_id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'score', 'status'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function scoreHistories(): HasMany
    {
        return $this->hasMany(ScoreHistory::class);
    }
}
