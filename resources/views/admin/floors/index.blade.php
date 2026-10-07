@extends('layouts.app')
@section('title', 'ชั้น | KKU DORM')
@section('content')
<h1>ชั้น</h1>
@can('create', App\Models\Floor::class)<a href="{{ route('admin.floors.create') }}">เพิ่มชั้น</a>@endcan
<form method="GET" class="filters" action="{{ route('admin.floors.index') }}">
<label>ค้นหา <input name="search" value="{{ request('search') }}" maxlength="100"></label>
<label>อาคาร <select name="building_id"><option value="">ทั้งหมด</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(request('building_id') == $parent->id)>{{ $parent->dorm->name }} / {{ $parent->name }}</option>@endforeach</select></label><button type="submit">ค้นหา / กรอง</button><a href="{{ route('admin.floors.index') }}">ล้างตัวกรอง</a>
</form>
<div class="table-wrapper"><table><thead><tr><th>อาคาร</th><th>หมายเลข</th><th>ชื่อ</th><th>รายละเอียด</th></tr></thead><tbody>
@forelse ($records as $record)<tr><td>{{ $record->building->dorm->name }} / {{ $record->building->name }}</td><td>{{ $record->number ?? '—' }}</td><td>{{ $record->name ?? '—' }}</td><td><a href="{{ route('admin.floors.show', $record) }}">ดูข้อมูล</a></td></tr>@empty<tr><td colspan="4">ไม่พบข้อมูล</td></tr>@endforelse
</tbody></table></div>
{{ $records->links() }}
@endsection
