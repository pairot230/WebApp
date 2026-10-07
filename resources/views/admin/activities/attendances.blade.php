@extends('layouts.app')
@section('content')
    <h1>เช็กชื่อ: {{ $activity->title }}</h1>
    <p>สถานะ: {{ App\Models\Activity::STATUS_LABELS[$activity->status] ?? $activity->status }} / {{ $activity->score }} คะแนน</p>
    <div id="attendance-scanner" data-url="{{ route('admin.activities.attendances.store', $activity) }}" data-preview-url="{{ route('admin.activities.attendances.preview', $activity) }}" data-csrf="{{ csrf_token() }}">
        <p>สแกน QR ประจำตัวสมาชิก และตรวจชื่อที่แสดงกับเจ้าของ QR ก่อนเช็กชื่อ กล้องบนโทรศัพท์ต้องเปิดหน้านี้ผ่าน HTTPS</p>
        <div id="qr-reader" style="max-width: 420px"></div>
        <div class="actions"><button id="start-camera" type="button">เปิดกล้อง</button><button id="stop-camera" type="button" disabled>ปิดกล้อง</button></div>
        <label class="field">หรืออ่าน QR จากรูปภาพ<input id="qr-image" type="file" accept="image/*"></label>
        <div id="scan-preview" hidden>
            <p id="scan-member"></p>
            <button id="confirm-check-in" type="button">ยืนยันเช็กชื่อ</button>
            <button id="cancel-check-in" type="button">ยกเลิก</button>
        </div>
        <p id="scan-status" role="status" aria-live="polite"></p>
    </div>
    <a href="{{ route('admin.activities.attendances.index', $activity) }}">รีเฟรชรายชื่อ</a>
    <div class="table-wrapper"><table><thead><tr><th>รหัสนักศึกษา</th><th>ชื่อ</th><th>เวลาเช็กชื่อ (เวลาไทย)</th><th>คะแนน</th><th>ผู้เช็กชื่อ</th></tr></thead><tbody>
        @forelse ($attendances as $attendance)
            <tr>
                @can('view', $attendance->user)
                    <td>{{ $attendance->user->student_id }}</td><td>{{ $attendance->user->name }}</td>
                @else
                    <td colspan="2">ข้อมูลสมาชิกที่จำกัดสิทธิ์</td>
                @endcan
                <td>{{ $attendance->checked_in_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }}</td><td>{{ $attendance->scoreHistory?->score ?? '—' }}</td><td>{{ $attendance->checkedInBy->name }}</td>
            </tr>
        @empty
            <tr><td colspan="5">ยังไม่มีผู้เข้าร่วม</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $attendances->links() }}
    <a href="{{ route('admin.activities.show', $activity) }}">กลับกิจกรรม</a>
    <script src="{{ asset('js/vendor/html5-qrcode.min.js') }}" defer></script>
    <script src="{{ asset('js/attendance-scanner.js') }}" defer></script>
@endsection
