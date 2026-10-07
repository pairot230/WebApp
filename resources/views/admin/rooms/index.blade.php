@extends('layouts.app')
@section('title', 'ห้องพัก | KKU DORM')
@section('content')
<h1>ห้องพัก</h1>
@can('create', App\Models\Room::class)<a href="{{ route('admin.rooms.create') }}">เพิ่มห้องพัก</a>@endcan
<form method="GET" class="filters" action="{{ route('admin.rooms.index') }}">
<label>ค้นหา <input name="search" value="{{ request('search') }}" maxlength="100"></label>
<label>ชั้น <select name="floor_id"><option value="">ทั้งหมด</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(request('floor_id') == $parent->id)>{{ $parent->building->dorm->name }} / {{ $parent->building->name }} / ชั้น {{ $parent->number }}</option>@endforeach</select></label><label>สถานะ <select name="status"><option value="">ทั้งหมด</option><option value="active" @selected(request('status') === 'active')>ใช้งาน</option><option value="inactive" @selected(request('status') === 'inactive')>ปิดใช้งาน</option></select></label><button type="submit">ค้นหา / กรอง</button><a href="{{ route('admin.rooms.index') }}">ล้างตัวกรอง</a>
</form>
<div class="table-wrapper"><table><thead><tr><th>ชั้น</th><th>หมายเลข</th><th>ความจุ</th><th>สถานะ</th><th>ผู้พัก</th><th>รายละเอียด</th></tr></thead><tbody>
@forelse ($records as $record)<tr><td>{{ $record->floor->building->dorm->name }} / {{ $record->floor->building->name }} / ชั้น {{ $record->floor->number }}</td><td>{{ $record->number ?? '—' }}</td><td>{{ $record->capacity ?? '—' }}</td><td>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</td><td>{{ $record->occupied_count }} / 2 คน</td><td><a href="{{ route('admin.rooms.show', $record) }}">ดูข้อมูล</a></td></tr>@empty<tr><td colspan="5">ไม่พบข้อมูล</td></tr>@endforelse
</tbody></table></div>
{{ $records->links() }}
@endsection
