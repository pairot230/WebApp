@extends('layouts.app')
@section('title', 'อาคาร | KKU DORM')
@section('content')
<h1>ข้อมูลอาคาร</h1><dl><dt>หอพัก</dt><dd>{{ $record->dorm->name }}</dd><dt>รหัส</dt><dd>{{ $record->code ?? '—' }}</dd><dt>ชื่อ</dt><dd>{{ $record->name ?? '—' }}</dd><dt>สถานะ</dt><dd>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</dd></dl>
<div class="actions">
@can('update', $record)<a href="{{ route('admin.buildings.edit', $record) }}">แก้ไข</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route('admin.buildings.destroy', $record) }}" onsubmit="return confirm('ยืนยันการลบข้อมูล?')">@csrf @method('DELETE')<button type="submit">ลบ</button></form>@endcan
<a href="{{ route('admin.buildings.index') }}">กลับรายการ</a>
</div>
@endsection
