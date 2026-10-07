@extends('layouts.app')
@section('content')
    <h1>กิจกรรมในหอพักของฉัน</h1>
    @forelse ($activities as $activity)
        <article style="border-bottom:1px solid #ddd;padding:16px 0">
            <h2>{{ $activity->title }}</h2><p style="white-space:pre-wrap">{{ $activity->description }}</p>
            <p>{{ $activity->starts_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} – {{ $activity->ends_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} (เวลาไทย)</p>
            <p>{{ $activity->location }} / ปี {{ $activity->academicYear->year }} / {{ $activity->score }} คะแนน</p>
            <p>{{ App\Models\Activity::STATUS_LABELS[$activity->status] ?? $activity->status }} / {{ $activity->attended ? 'เช็กชื่อแล้ว' : 'ยังไม่ได้เช็กชื่อ' }}</p>
        </article>
    @empty
        <p>ยังไม่มีกิจกรรม</p>
    @endforelse
    {{ $activities->links() }}
@endsection
