@extends('layouts.app')
@section('title', 'รายละเอียด Audit Log | KKU DORM')
@section('content')
    <h1>รายละเอียด Audit Log #{{ $log->id }}</h1>
    <dl>
        <dt>User / ผู้ดำเนินการ</dt><dd>{{ $log->actor_snapshot['name'] ?? $log->actor?->name ?? 'ระบบ / ไม่พบบัญชีเดิม' }} @if ($log->actor_snapshot['id'] ?? $log->actor_id) (#{{ $log->actor_snapshot['id'] ?? $log->actor_id }}) @endif</dd>
        <dt>Action</dt><dd>{{ $log->actionLabel() }} ({{ $log->action }})</dd>
        <dt>Target</dt><dd>{{ App\Models\AuditLog::TARGET_LABELS[$log->target_type] ?? $log->target_type }} #{{ $log->target_id }}</dd>
        <dt>Date / Time (เวลาไทย)</dt><dd>{{ $log->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i:s') }}</dd>
    </dl>
    <h2>Old Data</h2>
    <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $log->old_values === null ? 'ไม่มีข้อมูลเดิม (เช่น การสร้างรายการใหม่)' : json_encode(App\Models\AuditLog::safeValues($log->old_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    <h2>New Data</h2>
    <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $log->new_values === null ? 'ไม่มีข้อมูลใหม่ (เช่น การลบรายการ)' : json_encode(App\Models\AuditLog::safeValues($log->new_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    <a href="{{ route('superadmin.audit.index') }}">กลับรายการ Audit Log</a>
@endsection
