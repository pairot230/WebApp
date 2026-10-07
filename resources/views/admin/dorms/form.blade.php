@extends('layouts.app')
@section('title', 'หอพัก | KKU DORM')
@section('content')
<h1>{{ $record->exists ? 'แก้ไข' : 'เพิ่ม' }}หอพัก</h1>
<form method="POST" action="{{ $record->exists ? route('admin.dorms.update', $record) : route('admin.dorms.store') }}">
@csrf @if ($record->exists) @method('PUT') @endif
<label class="field">รหัส<input type="text" name="code" value="{{ old('code', $record->code) }}" maxlength="255" required></label>
<label class="field">ชื่อ<input type="text" name="name" value="{{ old('name', $record->name) }}" maxlength="255" required></label>
<label class="field">รายละเอียด<textarea name="description" maxlength="5000">{{ old('description', $record->description) }}</textarea></label>
<label class="field">สถานะ<select name="is_active"><option value="1" @selected(old('is_active', $record->is_active ?? 1) == 1)>ใช้งาน</option><option value="0" @selected(old('is_active', $record->is_active ?? 1) == 0)>ปิดใช้งาน</option></select></label>
<button type="submit">บันทึกข้อมูล</button> <a href="{{ route('admin.dorms.index') }}">ยกเลิก</a>
</form>
@endsection
