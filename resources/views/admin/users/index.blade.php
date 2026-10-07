@extends('layouts.app')
@section('title', 'สมาชิก | KKU DORM')
@section('content')
    <h1>สมาชิก</h1>
    @can('create', App\Models\User::class)<a href="{{ route('admin.users.create') }}">เพิ่มสมาชิก</a>@endcan
    <form method="GET" action="{{ route('admin.users.index') }}" class="filters">
        <label>ค้นหา <input name="search" value="{{ request('search') }}" maxlength="100" placeholder="รหัส / ชื่อ / Email / โทรศัพท์"></label>
        <label>หอพัก <select name="dorm_id"><option value="">ทั้งหมด</option>@foreach ($dorms as $dorm)<option value="{{ $dorm->id }}" @selected(request('dorm_id') == $dorm->id)>{{ $dorm->name }}</option>@endforeach</select></label>
        <label>ห้องพัก <select name="room_id"><option value="">ทั้งหมด</option>@foreach ($rooms as $room)<option value="{{ $room->id }}" @selected(request('room_id') == $room->id)>{{ $room->floor->building->dorm->name }} / {{ $room->floor->building->name }} / {{ $room->number }}</option>@endforeach</select></label>
        @if (auth()->user()->role === 'superadmin')
            <label>สิทธิ์ <select name="role"><option value="">ทั้งหมด</option>@foreach (['user', 'admin', 'superadmin'] as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select></label>
        @endif
        <label>สถานะ <select name="status"><option value="">ทั้งหมด</option><option value="active" @selected(request('status') === 'active')>ใช้งาน</option><option value="inactive" @selected(request('status') === 'inactive')>ระงับ</option></select></label>
        <button type="submit">ค้นหา / กรอง</button><a href="{{ route('admin.users.index') }}">ล้างตัวกรอง</a>
    </form>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>ชื่อ - นามสกุล</th><th>รหัสนักศึกษา</th><th>Email / โทรศัพท์</th><th>ห้อง</th><th>Role / Status</th><th>รายละเอียด</th></tr></thead>
            <tbody>
                @forelse ($members as $member)
                    <tr><td>{{ $member->name }}</td><td>{{ $member->student_id ?? 'ไม่ได้ระบุ' }}</td>
                        <td>{{ $member->email }}<br>{{ $member->phone ?? '—' }}</td><td>{{ $member->room?->number ?? '—' }}</td>
                        <td>{{ $member->role }} / {{ $member->is_active ? 'ใช้งาน' : 'ระงับ' }}</td>
                        <td><a href="{{ route('admin.users.show', $member) }}">ดูข้อมูล</a></td></tr>
                @empty
                    <tr><td colspan="6">ไม่พบสมาชิก</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $members->links() }}
@endsection
