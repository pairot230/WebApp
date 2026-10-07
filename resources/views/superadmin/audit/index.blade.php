@extends('layouts.app')
@section('title', 'Audit Log | KKU DORM')
@section('content')
    <h1>Audit Log</h1>
    <form method="GET" class="filters">
        <label>ผู้ดำเนินการ<select name="actor_id"><option value="">ทุกคน</option>
            @foreach ($actors as $actor)<option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>{{ $actor->name }}</option>@endforeach
        </select></label>
        <label>Action<select name="action"><option value="">ทั้งหมด</option>
            @foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ App\Models\AuditLog::ACTION_LABELS[$action] ?? $action }} ({{ $action }})</option>@endforeach
        </select></label>
        <label>Target<select name="target_type"><option value="">ทุกประเภท</option>
            @foreach (App\Models\AuditLog::TARGET_LABELS as $value => $label)<option value="{{ $value }}" @selected(request('target_type') === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label>รหัส Target<input type="number" name="target_id" value="{{ request('target_id') }}" min="1"></label>
        <label>ตั้งแต่ (เวลาไทย)<input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label>ถึง (เวลาไทย)<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button>ค้นหา / กรอง</button><a href="{{ route('superadmin.audit.index') }}">ล้างตัวกรอง</a>
    </form>
    <div class="table-wrapper"><table><thead><tr><th>วัน / เวลา (เวลาไทย)</th><th>ผู้ดำเนินการ</th><th>Action</th><th>Target</th><th>ข้อมูล</th></tr></thead><tbody>
        @forelse ($logs as $log)
            <tr><td>{{ $log->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }}</td><td>{{ $log->actor_snapshot['name'] ?? $log->actor?->name ?? 'ระบบ / ไม่พบบัญชีเดิม' }}</td><td>{{ $log->actionLabel() }}</td><td>{{ App\Models\AuditLog::TARGET_LABELS[$log->target_type] ?? $log->target_type }} #{{ $log->target_id }}</td><td><a href="{{ route('superadmin.audit.show', $log) }}">Old / New Data</a></td></tr>
        @empty
            <tr><td colspan="5">ยังไม่มี Audit Log ตามตัวกรอง</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $logs->links() }}
@endsection
