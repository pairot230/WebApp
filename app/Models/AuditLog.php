<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'action', 'target_type', 'target_id', 'old_values', 'new_values'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_LABELS = [
        'score.adjusted' => 'ปรับคะแนน', 'attendance.created' => 'เช็กชื่อและเพิ่มคะแนน',
        'user.role_changed' => 'เปลี่ยนสิทธิ์ Admin', 'setup.superadmin' => 'ตั้งค่า SuperAdmin',
        'finance.created' => 'เพิ่มการเงิน', 'finance.updated' => 'แก้ไขการเงิน', 'finance.voided' => 'ยกเลิกการเงิน',
        'membership.synced' => 'อัปเดตค่าส่วนกลาง', 'repair.created' => 'แจ้งซ่อม', 'repair.status_updated' => 'เปลี่ยนสถานะงานซ่อม',
        'complaint.created' => 'แจ้งเรื่องร้องเรียน', 'complaint.updated' => 'แก้ไขเรื่องร้องเรียน',
        'user.created' => 'เพิ่มสมาชิก', 'user.updated' => 'แก้ไขสมาชิก', 'user.deleted' => 'ลบสมาชิก',
        'activity.created' => 'เพิ่มกิจกรรม', 'activity.updated' => 'แก้ไขกิจกรรม', 'activity.deleted' => 'ลบกิจกรรม',
    ];

    public const TARGET_LABELS = [
        User::class => 'สมาชิก', ScoreHistory::class => 'ประวัติคะแนน', Attendance::class => 'Attendance',
        FinancialTransaction::class => 'รายการการเงิน', MembershipPayment::class => 'ค่าส่วนกลาง',
        RepairRequest::class => 'งานแจ้งซ่อม', Complaint::class => 'เรื่องร้องเรียน', Activity::class => 'กิจกรรม',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            $actor = $log->actor_id !== null ? User::find($log->actor_id) : null;
            $log->actor_snapshot = $actor ? ['id' => $actor->id, 'name' => $actor->name] : null;
            $log->old_values = self::safeValues($log->old_values);
            $log->new_values = self::safeValues($log->new_values);
        });
        static::updating(function (): void {
            throw new \LogicException('Audit logs are append-only.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Audit logs are append-only.');
        });
    }

    public static function safeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), ['password', 'password_confirmation', 'google_id', 'qr_token', 'credential', 'id_token', 'access_token', 'refresh_token', 'client_secret', 'remember_token'], true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::safeValues($value);
            }
        }

        return $values;
    }

    public function actionLabel(): string
    {
        if ($this->action === 'user.role_changed') {
            return ($this->new_values['role'] ?? null) === 'admin' ? 'แต่งตั้ง Admin' : 'ถอดถอน Admin';
        }

        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'actor_snapshot' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
