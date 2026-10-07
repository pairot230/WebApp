@extends('layouts.app')
@section('title', 'จัดการสิทธิ์ Admin | KKU DORM')
@section('content')
    <h1>จัดการสิทธิ์ Admin</h1>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>สมาชิก</th><th>สิทธิ์ปัจจุบัน</th><th>เปลี่ยนสิทธิ์</th></tr></thead>
            <tbody>
                @forelse ($members as $member)
                    <tr><td>{{ $member->name }}<br>{{ $member->email }}</td><td>{{ $member->role }}</td>
                        <td>
                            <form method="POST" action="{{ route('superadmin.users.role', $member) }}" onsubmit="return confirm('ยืนยันการเปลี่ยนสิทธิ์สมาชิก?')">
                                @csrf @method('PATCH')
                                <input type="hidden" name="role" value="{{ $member->role === 'admin' ? 'user' : 'admin' }}">
                                <button type="submit">{{ $member->role === 'admin' ? 'ถอดถอน Admin' : 'แต่งตั้ง Admin' }}</button>
                            </form>
                        </td></tr>
                @empty
                    <tr><td colspan="3">ยังไม่มีสมาชิกที่สามารถเปลี่ยนสิทธิ์ได้</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $members->links() }}
@endsection
