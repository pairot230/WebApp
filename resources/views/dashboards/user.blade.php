@extends('layouts.app')
@section('title', 'Dashboard ของฉัน | KKU DORM')
@section('content')
    <h1>Dashboard ของฉัน</h1>
    @include('dashboards.header')
    <div class="dashboard-grid">
        <section class="dashboard-stat">คะแนนของฉัน<span class="dashboard-value">{{ $score }}</span><a href="{{ route('member.scores', request()->only('academic_year_id')) }}">ดูประวัติคะแนน</a></section>
        <section class="dashboard-stat">กิจกรรมที่เข้าร่วม<span class="dashboard-value">{{ $attendanceCount }}</span><a href="{{ route('member.activities') }}">ดูกิจกรรม</a></section>
        <section class="dashboard-stat">เรื่องร้องเรียนของฉัน<span class="dashboard-value">{{ $complaintCount }}</span><a href="{{ route('member.complaints.index') }}">ติดตามเรื่องร้องเรียน</a></section>
        <section class="dashboard-stat">งานแจ้งซ่อมของฉัน<span class="dashboard-value">{{ $repairCount }}</span><a href="{{ route('member.repairs.index') }}">ติดตามงานซ่อม</a></section>
    </div>
    <p>คะแนนและกิจกรรมตามปีที่เลือก ส่วนเรื่องร้องเรียนและงานแจ้งซ่อมเป็นประวัติทั้งหมดของฉัน</p>
    <section class="dashboard-section">
        <h2>ห้องของฉัน</h2>
        @if ($member->room)
            <p>{{ $member->dorm?->name }} / {{ $member->room->floor->building->name }} / ชั้น {{ $member->room->floor->number }} / ห้อง {{ $member->room->number }} (ห้องละ {{ $member->room->capacity }} คน)</p>
        @else
            <p>ยังไม่กำหนดห้องพัก</p>
        @endif
        <a href="{{ route('member.profile') }}">ดูข้อมูลของฉัน</a>
    </section>
    <section class="dashboard-section">
        <h2>QR ของฉัน</h2>
        <img src="{{ route('member.qr.image') }}" alt="QR ประจำตัวสำหรับเช็กชื่อกิจกรรม" width="220" height="220" style="max-width:100%;height:auto">
        <p>แสดง QR ให้เจ้าหน้าที่เช็กชื่อกิจกรรม</p><a href="{{ route('member.qr') }}">เปิด QR ขนาดใหญ่</a>
    </section>
    <section class="dashboard-section">
        <h2>กิจกรรมที่เปิดอยู่</h2>
        @forelse ($upcoming as $activity)
            <p>{{ $activity->title }} / {{ $activity->starts_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }} (เวลาไทย) / {{ $activity->location }} / {{ $activity->score }} คะแนน</p>
        @empty
            <p>ยังไม่มีกิจกรรมเปิดให้เข้าร่วมตามปีที่เลือก</p>
        @endforelse
    </section>
    <section class="dashboard-section">
        <h2>เรื่องร้องเรียนล่าสุดของฉัน</h2>
        @forelse ($recentComplaints as $complaint)
            <p><a href="{{ route('member.complaints.show', $complaint) }}">{{ $complaint->title }}</a> / {{ App\Models\Complaint::STATUS_LABELS[$complaint->status] ?? $complaint->status }}</p>
        @empty
            <p>ยังไม่มีเรื่องร้องเรียน</p>
        @endforelse
    </section>
    <section class="dashboard-section">
        <h2>งานแจ้งซ่อมล่าสุดของฉัน</h2>
        @forelse ($recentRepairs as $repair)
            <p><a href="{{ route('member.repairs.show', $repair) }}">{{ App\Models\RepairRequest::CATEGORIES[$repair->category] ?? $repair->category }} / {{ $repair->room_label }}</a> / {{ App\Models\RepairRequest::STATUS_LABELS[$repair->status] ?? $repair->status }}</p>
        @empty
            <p>ยังไม่มีงานแจ้งซ่อม</p>
        @endforelse
    </section>
    <section class="dashboard-section">
        <h2>การเงินที่เปิดเผย</h2><p>ยอดรวมเฉพาะรายการเปิดเผยและบันทึกแล้วตามปีที่เลือก</p>
        <dl><dt>รายรับทั้งหมด</dt><dd>{{ $totals['income'] }} บาท</dd><dt>รายจ่ายทั้งหมด</dt><dd>{{ $totals['expense'] }} บาท</dd><dt>ยอดคงเหลือ</dt><dd>{{ $totals['balance'] }} บาท</dd></dl>
        <a href="{{ route('member.finance', request()->only('academic_year_id')) }}">ดูการเงินที่เปิดเผย</a>
    </section>
@endsection
