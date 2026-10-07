<dl>
    <dt>หอพัก</dt><dd>{{ $complaint->dorm->name }}</dd>
    <dt>รายละเอียด</dt><dd style="white-space: pre-wrap">{{ $complaint->description }}</dd>
    <dt>สถานะ</dt><dd>{{ App\Models\Complaint::STATUS_LABELS[$complaint->status] ?? $complaint->status }}</dd>
    <dt>ผู้รับผิดชอบ</dt><dd>{{ $complaint->assignee?->name ?? 'ยังไม่มอบหมาย' }}</dd>
    <dt>หมายเหตุการดำเนินการ</dt><dd style="white-space: pre-wrap">{{ $complaint->note ?? 'ยังไม่มีหมายเหตุ' }}</dd>
    <dt>วันที่แจ้ง (เวลาไทย)</dt><dd>{{ $complaint->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</dd>
    <dt>อัปเดตล่าสุด (เวลาไทย)</dt><dd>{{ $complaint->updated_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</dd>
</dl>
