<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'dorm_id', 'room_id', 'reporter_name', 'reporter_type', 'room_label', 'category', 'description', 'phone', 'email', 'appointment_date', 'appointment_time', 'status', 'assigned_to', 'note'])]
class RepairRequest extends Model
{
    public const STATUS_LABELS = ['new' => 'แจ้งใหม่', 'received' => 'รับเรื่องแล้ว', 'in_progress' => 'กำลังดำเนินการ', 'waiting_parts' => 'รออะไหล่', 'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิก'];

    public const REPORTER_TYPES = ['student' => 'นักศึกษา', 'advisor' => 'ที่ปรึกษาหอพัก', 'staff' => 'เจ้าหน้าที่หอ'];

    public const CATEGORIES = ['electrical' => 'งานไฟฟ้า', 'plumbing' => 'งานประปา', 'wood_masonry' => 'งานไม้ / งานปูน', 'other' => 'อื่นๆ'];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'user_id' => 'integer', 'dorm_id' => 'integer', 'room_id' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
