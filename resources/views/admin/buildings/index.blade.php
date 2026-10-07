@extends('layouts.app')
@section('title', 'อาคาร | KKU DORM')
@section('content')
<h1>อาคาร</h1>
@can('create', App\Models\Building::class)<a href="{{ route('admin.buildings.create') }}">เพิ่มอาคาร</a>@endcan
<form method="GET" class="filters" action="{{ route('admin.buildings.index') }}">
<label>ค้นหา <input name="search" value="{{ request('search') }}" maxlength="100"></label>
<label>หอพัก <select name="dorm_id"><option value="">ทั้งหมด</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(request('dorm_id') == $parent->id)>{{ $parent->name }} ({{ $parent->code }})</option>@endforeach</select></label><label>สถานะ <select name="status"><option value="">ทั้งหมด</option><option value="active" @selected(request('status') === 'active')>ใช้งาน</option><option value="inactive" @selected(request('status') === 'inactive')>ปิดใช้งาน</option></select></label><button type="submit">ค้นหา / กรอง</button><a href="{{ route('admin.buildings.index') }}">ล้างตัวกรอง</a>
</form>
<div class="table-wrapper"><table><thead><tr><th>หอพัก</th><th>รหัส</th><th>ชื่อ</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead><tbody>
@forelse ($records as $record)<tr><td>{{ $record->dorm->name }}</td><td>{{ $record->code ?? '—' }}</td><td>{{ $record->name ?? '—' }}</td><td>{{ $record->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}</td><td><a href="{{ route('admin.buildings.show', $record) }}">ดูข้อมูล</a></td></tr>@empty<tr><td colspan="5">ไม่พบข้อมูล</td></tr>@endforelse
</tbody></table></div>
{{ $records->links() }}
@endsection
