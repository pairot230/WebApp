@extends('layouts.app')
@section('title', 'จัดการสถานะงานซ่อม | KKU DORM')
@section('content')
    <h1>จัดการสถานะงานซ่อม</h1>
    @include('member.repairs.details')
    <form method="POST" action="{{ route('admin.repairs.update', $repair) }}">
        @csrf @method('PATCH')
        <label class="field">สถานะงานซ่อม<select name="status" required>
            @foreach (App\Models\RepairRequest::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', $repair->status) === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <button type="submit">อัปเดตสถานะ</button>
    </form>
    <a href="{{ route('admin.repairs.index') }}">กลับรายการ</a>
@endsection
