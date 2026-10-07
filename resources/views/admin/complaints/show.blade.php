@extends('layouts.app')
@section('title', 'ดำเนินการเรื่องร้องเรียน | KKU DORM')
@section('content')
    <h1>{{ $complaint->title }}</h1>
    <p>ผู้แจ้ง: {{ $complaint->reporter->name }}</p>
    @include('member.complaints.details')
    <h2>บันทึกการดำเนินการ</h2>
    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}">
        @csrf @method('PATCH')
        <label class="field">สถานะ<select name="status" required>
            @foreach (App\Models\Complaint::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', $complaint->status) === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field">ผู้รับผิดชอบ<select name="assigned_to"><option value="">ยังไม่มอบหมาย</option>
            @foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected(old('assigned_to', $complaint->assigned_to) == $assignee->id)>{{ $assignee->name }}</option>@endforeach
        </select></label>
        <label class="field">หมายเหตุการดำเนินการ<textarea name="note" rows="5" maxlength="10000">{{ old('note', $complaint->note) }}</textarea></label>
        <p>หมายเหตุนี้แสดงให้ผู้แจ้งเรื่องเห็นด้วย ผู้รับผิดชอบเลือกได้จากเจ้าหน้าที่ที่ใช้งานและมีสิทธิ์ในหอของเรื่องนี้</p>
        <button type="submit">บันทึกการดำเนินการ</button>
    </form>
    <a href="{{ route('admin.complaints.index') }}">กลับรายการ</a>
@endsection
