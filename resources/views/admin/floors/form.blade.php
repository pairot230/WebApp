@extends('layouts.app')
@section('title', 'ชั้น | KKU DORM')
@section('content')
<h1>{{ $record->exists ? 'แก้ไข' : 'เพิ่ม' }}ชั้น</h1>
<form method="POST" action="{{ $record->exists ? route('admin.floors.update', $record) : route('admin.floors.store') }}">
@csrf @if ($record->exists) @method('PUT') @endif
<label class="field">อาคาร<select name="building_id" required @disabled($record->exists)>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('building_id', $record->building_id) == $parent->id)>{{ $parent->dorm->name }} / {{ $parent->name }}</option>@endforeach</select></label>
@if ($record->exists)<input type="hidden" name="building_id" value="{{ $record->building_id }}"><p>ตำแหน่งในโครงสร้างเดิมไม่สามารถย้ายผ่านฟอร์มนี้ได้</p>@endif
<label class="field">หมายเลข<input type="number" name="number" value="{{ old('number', $record->number) }}" min="0" max="65535" required></label>
<label class="field">ชื่อ<input type="text" name="name" value="{{ old('name', $record->name) }}" maxlength="255" ></label>
<button type="submit">บันทึกข้อมูล</button> <a href="{{ route('admin.floors.index') }}">ยกเลิก</a>
</form>
@endsection
