@extends('layouts.app')
@section('title', 'ชั้น | KKU DORM')
@section('content')
<h1>ข้อมูลชั้น</h1><dl><dt>อาคาร</dt><dd>{{ $record->building->dorm->name }} / {{ $record->building->name }}</dd><dt>หมายเลข</dt><dd>{{ $record->number ?? '—' }}</dd><dt>ชื่อ</dt><dd>{{ $record->name ?? '—' }}</dd></dl>
<div class="actions">
@can('update', $record)<a href="{{ route('admin.floors.edit', $record) }}">แก้ไข</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route('admin.floors.destroy', $record) }}" onsubmit="return confirm('ยืนยันการลบข้อมูล?')">@csrf @method('DELETE')<button type="submit">ลบ</button></form>@endcan
<a href="{{ route('admin.floors.index') }}">กลับรายการ</a>
</div>
@endsection
