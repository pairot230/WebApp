@extends('layouts.app')
@section('title', 'ข้อมูลสมาชิก | KKU DORM')
@section('content')
    <h1>ข้อมูลสมาชิก</h1>
    <dl>
        <dt>ชื่อ - นามสกุล</dt><dd>{{ $member->name }}</dd>
        <dt>รหัสนักศึกษา</dt><dd>{{ $member->student_id ?? '—' }}</dd>
        <dt>Email</dt><dd>{{ $member->email }}</dd>
        <dt>โทรศัพท์</dt><dd>{{ $member->phone ?? '—' }}</dd>
        <dt>หอพัก</dt><dd>{{ $member->dorm?->name ?? '—' }}</dd>
        <dt>ห้องพัก</dt><dd>{{ $member->room?->number ?? '—' }}</dd>
        <dt>Role</dt><dd>{{ $member->role }}</dd>
        <dt>สถานะ</dt><dd>{{ $member->is_active ? 'ใช้งาน' : 'ระงับการใช้งาน' }}</dd>
    </dl>
    <div class="actions">
        <a href="{{ route('admin.users.scores.index', $member) }}">ประวัติ / ปรับคะแนน</a>
        @can('update', $member)<a href="{{ route('admin.users.edit', $member) }}">แก้ไข</a>@endcan
        @can('delete', $member)
            <form method="POST" action="{{ route('admin.users.destroy', $member) }}" onsubmit="return confirm('ยืนยันการลบสมาชิก?')">@csrf @method('DELETE')<button type="submit">ลบสมาชิก</button></form>
        @endcan
        <a href="{{ route('admin.users.index') }}">กลับรายการ</a>
    </div>
@endsection
