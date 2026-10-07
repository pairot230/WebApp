<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'dorm_id', 'title', 'description', 'status', 'assigned_to', 'note'])]
class Complaint extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'รอดำเนินการ',
        'reviewing' => 'กำลังตรวจสอบ',
        'in_progress' => 'กำลังดำเนินการ',
        'completed' => 'ดำเนินการเสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
    ];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'dorm_id' => 'integer', 'assigned_to' => 'integer'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
