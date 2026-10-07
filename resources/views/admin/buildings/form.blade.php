@extends('layouts.app')
@section('title', 'อาคาร | KKU DORM')
@section('content')
<h1>{{ $record->exists ? 'แก้ไข' : 'เพิ่ม' }}อาคาร</h1>
<form method="POST" action="{{ $record->exists ? route('admin.buildings.update', $record) : route('admin.buildings.store') }}">
@csrf @if ($record->exists) @method('PUT') @endif
<label class="field">หอพัก<select name="dorm_id" required @disabled($record->exists)>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('dorm_id', $record->dorm_id) == $parent->id)>{{ $parent->name }} ({{ $parent->code }})</option>@endforeach</select></label>
@if ($record->exists)<input type="hidden" name="dorm_id" value="{{ $record->dorm_id }}"><p>ตำแหน่งในโครงสร้างเดิมไม่สามารถย้ายผ่านฟอร์มนี้ได้</p>@endif
<label class="field">รหัส<input type="text" name="code" value="{{ old('code', $record->code) }}" maxlength="255" required></label>
<label class="field">ชื่อ<input type="text" name="name" value="{{ old('name', $record->name) }}" maxlength="255" required></label>
<label class="field">สถานะ<select name="is_active"><option value="1" @selected(old('is_active', $record->is_active ?? 1) == 1)>ใช้งาน</option><option value="0" @selected(old('is_active', $record->is_active ?? 1) == 0)>ปิดใช้งาน</option></select></label>
<button type="submit">บันทึกข้อมูล</button> <a href="{{ route('admin.buildings.index') }}">ยกเลิก</a>
</form>
@endsection
