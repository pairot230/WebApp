@extends('layouts.app')
@section('title', 'แจ้งเรื่องร้องเรียน | KKU DORM')
@section('content')
    <h1>แจ้งเรื่องร้องเรียน</h1>
    <p>หอพัก: {{ $dorm->name }}</p>
    <form method="POST" action="{{ route('member.complaints.store') }}" onsubmit="const button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='กำลังส่ง…';">
        @csrf
        <label class="field">ชื่อเรื่อง<input name="title" value="{{ old('title') }}" required maxlength="255"></label>
        <label class="field">รายละเอียด<textarea name="description" rows="6" required maxlength="10000">{{ old('description') }}</textarea></label>
        <button type="submit">ส่งเรื่องร้องเรียน</button>
        <a href="{{ route('member.complaints.index') }}">กลับรายการ</a>
    </form>
@endsection
