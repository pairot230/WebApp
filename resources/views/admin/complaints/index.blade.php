@extends('layouts.app')
@section('title', 'จัดการเรื่องร้องเรียน | KKU DORM')
@section('content')
    <h1>จัดการเรื่องร้องเรียน</h1>
    @include('member.complaints.filters')
    <div class="table-wrapper"><table><thead><tr><th>เรื่อง</th><th>ผู้แจ้ง</th><th>หอพัก</th><th>สถานะ</th><th>ผู้รับผิดชอบ</th></tr></thead><tbody>
        @forelse ($complaints as $complaint)
            <tr><td><a href="{{ route('admin.complaints.show', $complaint) }}">{{ $complaint->title }}</a></td><td>{{ $complaint->reporter->name }}</td><td>{{ $complaint->dorm->name }}</td><td>{{ App\Models\Complaint::STATUS_LABELS[$complaint->status] ?? $complaint->status }}</td><td>{{ $complaint->assignee?->name ?? 'ยังไม่มอบหมาย' }}</td></tr>
        @empty
            <tr><td colspan="5">ไม่พบเรื่องร้องเรียน</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $complaints->links() }}
@endsection
