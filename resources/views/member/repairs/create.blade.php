@extends('layouts.app')
@section('title', 'แจ้งซ่อม | KKU DORM')
@section('content')
    <h1>แจ้งซ่อม</h1><p>หอพัก: {{ $member->dorm->name }}</p>
    <form method="POST" action="{{ route('member.repairs.store') }}" onsubmit="const button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='กำลังบันทึก…';">
        @csrf
        <label class="field">ชื่อ - สกุล<input name="reporter_name" value="{{ old('reporter_name', $member->name) }}" required maxlength="255"></label>
        <label class="field">สถานะผู้แจ้งซ่อม<select name="reporter_type" required>
            @foreach (App\Models\RepairRequest::REPORTER_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('reporter_type', $member->role === 'user' ? 'student' : 'staff') === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field">ห้องผู้แจ้งซ่อม<input name="room_label" value="{{ old('room_label', $member->room?->number) }}" required maxlength="255" placeholder="เช่น 304 หรือสำนักงานหอพัก"></label>
        <label class="field">ประเภทงานแจ้งซ่อม<select name="category" required><option value="">เลือกประเภทงาน</option>
            @foreach (App\Models\RepairRequest::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field">ลักษณะการชำรุด / สถานที่ชำรุด<textarea name="description" rows="5" required maxlength="10000">{{ old('description') }}</textarea></label>
        <label class="field">เบอร์โทรศัพท์<input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" required maxlength="30"></label>
        <label class="field">Email<input type="email" name="email" value="{{ old('email', $member->email) }}" required maxlength="255"></label>
        <label class="field">วันนัดหมาย<input type="date" name="appointment_date" value="{{ old('appointment_date') }}" min="{{ now('Asia/Bangkok')->toDateString() }}" required></label>
        <label class="field">เวลานัดหมาย (เวลาไทย)<input type="time" name="appointment_time" value="{{ old('appointment_time') }}" required></label>
        <button type="submit">บันทึกข้อมูล</button><a href="{{ route('member.repairs.index') }}">กลับรายการ</a>
    </form>
@endsection
