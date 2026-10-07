@extends('layouts.app')
@section('title', 'จัดการกิจกรรม | KKU DORM')
@section('content')
    <h1>จัดการกิจกรรม</h1>
    <a href="{{ route('admin.activities.create') }}">สร้างกิจกรรม</a>
    <form class="filters" method="GET">
        <input name="search" value="{{ request('search') }}" placeholder="ค้นหาชื่อกิจกรรม" aria-label="ค้นหาชื่อกิจกรรม">
        <select name="status" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
            @foreach (App\Models\Activity::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="academic_year_id" aria-label="ปีการศึกษา"><option value="">ทุกปีการศึกษา</option>
            @foreach ($years as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
        </select>
        <button>ค้นหา</button><a href="{{ route('admin.activities.index') }}">ล้างตัวกรอง</a>
    </form>
    <div class="table-wrapper"><table><thead><tr><th>กิจกรรม</th><th>หอพัก / ปี</th><th>เริ่ม (เวลาไทย)</th><th>สถานะ</th><th>คะแนน</th><th>เข้าร่วม</th></tr></thead><tbody>
        @forelse ($activities as $activity)
            <tr><td><a href="{{ route('admin.activities.show', $activity) }}">{{ $activity->title }}</a></td><td>{{ $activity->dorm->name }} / {{ $activity->academicYear->year }}</td><td>{{ $activity->starts_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td><td>{{ App\Models\Activity::STATUS_LABELS[$activity->status] ?? $activity->status }}</td><td>{{ $activity->score }}</td><td>{{ $activity->attendances_count }}</td></tr>
        @empty
            <tr><td colspan="6">ไม่พบกิจกรรม</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $activities->links() }}
@endsection
