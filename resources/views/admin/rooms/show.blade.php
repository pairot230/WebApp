@extends('layouts.app')
@section('title', 'ห้องพัก | KKU DORM')
@section('content')
<h1>ข้อมูลห้องพัก</h1><dl><dt>ตำแหน่งห้องพัก</dt><dd>{{ $record->floor->building->dorm->name }} / {{ $record->floor->building->name }} / ชั้น {{ $record->floor->number }}</dd><dt>หมายเลข</dt><dd>{{ $record->number ?? '—' }}</dd><dt>ความจุ</dt><dd>{{ $record->capacity ?? '—' }}</dd><dt>สถานะ</dt><dd>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</dd></dl>
<h2>ผู้พักปัจจุบัน ({{ $record->users->count() }} / 2 คน)</h2>
<ul>
    @forelse ($record->users as $member)
        @can('view', $member)
            <li><a href="{{ route('admin.users.show', $member) }}">{{ $member->name }}</a></li>
        @else
            <li>ข้อมูลผู้พักจำกัดตามสิทธิ์</li>
        @endcan
    @empty
        <li>ห้องว่าง</li>
    @endforelse
</ul>
<div class="actions">
@can('update', $record)<a href="{{ route('admin.rooms.edit', $record) }}">แก้ไข</a>@endcan
@can('delete', $record)<form method="POST" action="{{ route('admin.rooms.destroy', $record) }}" onsubmit="return confirm('ยืนยันการลบข้อมูล?')">@csrf @method('DELETE')<button type="submit">ลบ</button></form>@endcan
<a href="{{ route('admin.rooms.index') }}">กลับรายการ</a>
</div>
@endsection
