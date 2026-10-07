<dl>
    <dt>ชื่อ - สกุล</dt><dd>{{ $repair->reporter_name }}</dd>
    <dt>หอพัก</dt><dd>{{ $repair->dorm->name }}</dd>
    <dt>สถานะผู้แจ้งซ่อม</dt><dd>{{ App\Models\RepairRequest::REPORTER_TYPES[$repair->reporter_type] ?? $repair->reporter_type }}</dd>
    <dt>ห้องผู้แจ้งซ่อม</dt><dd>{{ $repair->room_label }}</dd>
    <dt>ประเภทงานแจ้งซ่อม</dt><dd>{{ App\Models\RepairRequest::CATEGORIES[$repair->category] ?? $repair->category }}</dd>
    <dt>ลักษณะการชำรุด / สถานที่ชำรุด</dt><dd style="white-space: pre-wrap">{{ $repair->description }}</dd>
    <dt>เบอร์โทรศัพท์</dt><dd>{{ $repair->phone }}</dd>
    <dt>Email</dt><dd>{{ $repair->email }}</dd>
    <dt>วันนัดหมาย / เวลานัดหมาย (เวลาไทย)</dt><dd>{{ $repair->appointment_date->format('d/m/Y') }} {{ substr($repair->appointment_time, 0, 5) }}</dd>
    <dt>สถานะงานซ่อม</dt><dd>{{ App\Models\RepairRequest::STATUS_LABELS[$repair->status] ?? $repair->status }}</dd>
    <dt>วันที่แจ้ง (เวลาไทย)</dt><dd>{{ $repair->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</dd>
    <dt>อัปเดตล่าสุด (เวลาไทย)</dt><dd>{{ $repair->updated_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</dd>
</dl>
