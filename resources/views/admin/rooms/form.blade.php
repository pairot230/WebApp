@extends('layouts.app')
@section('title', 'ห้องพัก | KKU DORM')
@section('content')
<h1>{{ $record->exists ? 'แก้ไข' : 'เพิ่ม' }}ห้องพัก</h1>
<form method="POST" action="{{ $record->exists ? route('admin.rooms.update', $record) : route('admin.rooms.store') }}">
@csrf @if ($record->exists) @method('PUT') @endif
<label class="field">ชั้น<select name="floor_id" required @disabled($record->exists)>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('floor_id', $record->floor_id) == $parent->id)>{{ $parent->building->dorm->name }} / {{ $parent->building->name }} / ชั้น {{ $parent->number }}</option>@endforeach</select></label>
@if ($record->exists)<input type="hidden" name="floor_id" value="{{ $record->floor_id }}"><p>ตำแหน่งในโครงสร้างเดิมไม่สามารถย้ายผ่านฟอร์มนี้ได้</p>@endif
<label class="field">หมายเลข<input type="text" name="number" value="{{ old('number', $record->number) }}" maxlength="255" required></label>
<p>ความจุ: 2 คนต่อห้อง</p><input type="hidden" name="capacity" value="2">
<label class="field">สถานะ<select name="is_active"><option value="1" @selected(old('is_active', $record->is_active ?? 1) == 1)>ใช้งาน</option><option value="0" @selected(old('is_active', $record->is_active ?? 1) == 0)>ปิดใช้งาน</option></select></label>
<button type="submit">บันทึกข้อมูล</button> <a href="{{ route('admin.rooms.index') }}">ยกเลิก</a>
</form>
@endsection
