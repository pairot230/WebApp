@extends('layouts.app')
@section('title', 'ข้อมูลสมาชิก | KKU DORM')
@section('content')
    <h1>ข้อมูลสมาชิก</h1>
    <dl>
        <dt>ชื่อ - นามสกุล</dt><dd>{{ $member->name }}</dd>
        <dt>รหัสนักศึกษา</dt><dd>{{ $member->student_id ?? 'ไม่ได้ระบุ' }}</dd>
        <dt>คณะ</dt><dd>{{ $member->faculty ?? 'ไม่ได้ระบุ' }}</dd>
        <dt>KKU Mail</dt><dd>{{ $member->email }}</dd>
        <dt>โทรศัพท์</dt><dd>{{ $member->phone ?? 'ไม่ได้ระบุ' }}</dd>
        <dt>หอพัก</dt><dd>{{ $member->dorm?->name ?? 'ไม่ได้ระบุ' }}</dd>
        <dt>ห้องพัก</dt><dd>{{ $member->room?->number ?? 'ไม่ได้ระบุ' }}</dd>
        <dt>Role</dt><dd>{{ $member->role }}</dd>
        <dt>สถานะ</dt><dd>{{ $member->is_active ? 'ใช้งาน' : 'ระงับการใช้งาน' }}</dd>
    </dl>
@endsection
