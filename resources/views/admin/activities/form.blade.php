@extends('layouts.app')
@section('content')
    <h1>{{ $activity->exists ? 'แก้ไขกิจกรรม' : 'สร้างกิจกรรม' }}</h1>
    <form method="POST" action="{{ $activity->exists ? route('admin.activities.update', $activity) : route('admin.activities.store') }}">
        @csrf
        @if ($activity->exists) @method('PUT') @endif
        <label class="field">ชื่อกิจกรรม<input name="title" value="{{ old('title', $activity->title) }}" required maxlength="255"></label>
        <label class="field">รายละเอียด<textarea name="description" rows="4" maxlength="10000">{{ old('description', $activity->description) }}</textarea></label>
        <label class="field">หอพัก<select name="dorm_id" required>
            @foreach ($dorms as $dorm)<option value="{{ $dorm->id }}" @selected(old('dorm_id', $activity->dorm_id) == $dorm->id)>{{ $dorm->name }}</option>@endforeach
        </select></label>
        <label class="field">ปีการศึกษา<select name="academic_year_id" required>
            @foreach ($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $activity->academic_year_id) == $year->id)>{{ $year->year }}</option>@endforeach
        </select></label>
        <label class="field">เริ่ม (เวลาไทย)<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $activity->starts_at?->timezone('Asia/Bangkok')->format('Y-m-d\TH:i')) }}" required></label>
        <label class="field">สิ้นสุด (เวลาไทย)<input type="datetime-local" name="ends_at" value="{{ old('ends_at', $activity->ends_at?->timezone('Asia/Bangkok')->format('Y-m-d\TH:i')) }}" required></label>
        <label class="field">สถานที่<input name="location" value="{{ old('location', $activity->location) }}" required maxlength="255"></label>
        <label class="field">คะแนน<input type="number" name="score" value="{{ old('score', $activity->score) }}" min="0" max="10000" required></label>
        <label class="field">สถานะ<select name="status" required>
            @foreach (App\Models\Activity::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', $activity->status) === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <p>เช็กชื่อได้เฉพาะสถานะเปิดเช็กชื่อและอยู่ในช่วงเวลาที่กำหนด เมื่อมีผู้เข้าร่วมแล้วจะเปลี่ยนหอพัก ปีการศึกษา และคะแนนไม่ได้</p>
        <button type="submit">บันทึกข้อมูล</button><a href="{{ route('admin.activities.index') }}">กลับรายการ</a>
    </form>
@endsection
