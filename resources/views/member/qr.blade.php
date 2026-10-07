@extends('layouts.app')
@section('content')
    <h1>QR ของฉัน</h1>
    <p>{{ auth()->user()->name }} / {{ auth()->user()->student_id ?? 'ยังไม่มีรหัสนักศึกษา' }}</p>
    <img src="{{ route('member.qr.image') }}" alt="QR ประจำตัวสำหรับเช็กชื่อกิจกรรม" width="300" height="300" style="max-width:100%;height:auto">
    <p>แสดง QR นี้ให้เจ้าหน้าที่เช็กชื่อกิจกรรม กรุณาไม่ส่ง QR ให้ผู้อื่น</p>
@endsection
