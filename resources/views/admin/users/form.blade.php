@extends('layouts.app')
@section('title', 'จัดการสมาชิก | KKU DORM')
@section('content')
    <h1>{{ $member->exists ? 'แก้ไขสมาชิก' : 'เพิ่มสมาชิก' }}</h1>
    <form method="POST" action="{{ $member->exists ? route('admin.users.update', $member) : route('admin.users.store') }}">
        @csrf @if ($member->exists) @method('PUT') @endif
        <label class="field">รหัสนักศึกษา<input name="student_id" value="{{ old('student_id', $member->student_id) }}" maxlength="255" @required($member->role === 'user')></label>
        <label class="field">ชื่อ - นามสกุล<input name="name" value="{{ old('name', $member->name) }}" maxlength="255" required></label>
        <label class="field">Email<input type="email" name="email" value="{{ old('email', $member->email) }}" maxlength="255" required @readonly($member->google_id !== null)></label>
        <label class="field">โทรศัพท์<input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" maxlength="30"></label>
        <label class="field">หอพัก<select name="dorm_id"><option value="">ไม่ได้ระบุ</option>@foreach ($dorms as $dorm)<option value="{{ $dorm->id }}" @selected(old('dorm_id', $member->dorm_id) == $dorm->id)>{{ $dorm->name }}</option>@endforeach</select></label>
        <label class="field">ห้องพัก<select name="room_id"><option value="">ไม่ได้ระบุ</option>@foreach ($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id', $member->room_id) == $room->id)>{{ $room->floor->building->dorm->name }} / {{ $room->floor->building->name }} / ชั้น {{ $room->floor->number }} / ห้อง {{ $room->number }}{{ $room->is_active ? '' : ' (ปิดใช้งาน)' }}</option>@endforeach</select></label>
        <p>Role: {{ $member->role }} — เปลี่ยนสิทธิ์ผ่านหน้าจัดการสิทธิ์ Admin โดย SuperAdmin</p>
        <label class="field">สถานะ<select name="is_active"><option value="1" @selected(old('is_active', $member->is_active) == 1)>ใช้งาน</option><option value="0" @selected(old('is_active', $member->is_active) == 0)>ระงับการใช้งาน</option></select></label>
        <button type="submit">บันทึกข้อมูล</button> <a href="{{ route('admin.users.index') }}">ยกเลิก</a>
    </form>
@endsection
