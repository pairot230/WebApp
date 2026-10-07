@extends('layouts.app')
@section('title', 'หอพัก | KKU DORM')
@section('content')
<h1>ข้อมูลหอพัก</h1><dl><dt>รหัส</dt><dd>{{ $record->code ?? '—' }}</dd><dt>ชื่อ</dt><dd>{{ $record->name ?? '—' }}</dd><dt>รายละเอียด</dt><dd>{{ $record->description ?? '—' }}</dd><dt>สถานะ</dt><dd>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</dd></dl>
<div class="actions">
@can('update', $record)<a href="{{ route('admin.dorms.edit', $record) }}">แก้ไข</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route('admin.dorms.destroy', $record) }}" onsubmit="return confirm('ยืนยันการลบข้อมูล?')">@csrf @method('DELETE')<button type="submit">ลบ</button></form>@endcan
<a href="{{ route('admin.dorms.index') }}">กลับรายการ</a>
</div>
@endsection
