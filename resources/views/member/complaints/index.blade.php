@extends('layouts.app')
@section('title', 'เรื่องร้องเรียนของฉัน | KKU DORM')
@section('content')
    <h1>เรื่องร้องเรียนของฉัน</h1>
    @can('create', App\Models\Complaint::class)<a href="{{ route('member.complaints.create') }}">แจ้งเรื่องร้องเรียน</a>@endcan
    @include('member.complaints.filters')
    <div class="table-wrapper"><table><thead><tr><th>เรื่อง</th><th>หอพัก</th><th>สถานะ</th><th>วันที่แจ้ง (เวลาไทย)</th></tr></thead><tbody>
        @forelse ($complaints as $complaint)
            <tr><td><a href="{{ route('member.complaints.show', $complaint) }}">{{ $complaint->title }}</a></td><td>{{ $complaint->dorm->name }}</td><td>{{ App\Models\Complaint::STATUS_LABELS[$complaint->status] ?? $complaint->status }}</td><td>{{ $complaint->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td></tr>
        @empty
            <tr><td colspan="4">ไม่พบเรื่องร้องเรียน</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $complaints->links() }}
@endsection
