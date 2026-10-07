<form method="GET" class="filters">
    <input name="search" value="{{ request('search') }}" maxlength="100" placeholder="ค้นหาชื่อ ห้อง รายละเอียด โทรศัพท์ Email" aria-label="ค้นหางานซ่อม">
    <select name="status" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
        @foreach (App\Models\RepairRequest::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
    </select>
    <select name="category" aria-label="ประเภทงาน"><option value="">ทุกประเภทงาน</option>
        @foreach (App\Models\RepairRequest::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach
    </select>
    <button>ค้นหา / กรอง</button><a href="{{ url()->current() }}">ล้างตัวกรอง</a>
</form>
<div class="table-wrapper"><table><thead><tr><th>ผู้แจ้ง / ห้อง</th><th>ประเภท / รายละเอียด</th><th>นัดหมาย (เวลาไทย)</th><th>สถานะ</th><th>ดูข้อมูล</th></tr></thead><tbody>
    @forelse ($repairs as $repair)
        <tr><td>{{ $repair->reporter_name }} / {{ $repair->room_label }}</td><td>{{ App\Models\RepairRequest::CATEGORIES[$repair->category] ?? $repair->category }}<br>{{ Illuminate\Support\Str::limit($repair->description, 100) }}</td><td>{{ $repair->appointment_date->format('d/m/Y') }} {{ substr($repair->appointment_time, 0, 5) }}</td><td>{{ App\Models\RepairRequest::STATUS_LABELS[$repair->status] ?? $repair->status }}</td><td><a href="{{ route($isAdmin ? 'admin.repairs.show' : 'member.repairs.show', $repair) }}">รายละเอียด</a></td></tr>
    @empty
        <tr><td colspan="5">ไม่พบงานแจ้งซ่อม</td></tr>
    @endforelse
</tbody></table></div>
{{ $repairs->links() }}
