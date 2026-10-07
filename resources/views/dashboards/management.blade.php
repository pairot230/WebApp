@include('dashboards.header')
<p>หอพัก: {{ $dorm?->name ?? 'ยังไม่ตั้งค่าหอพัก' }}</p>
<p>สมาชิกและเรื่องร้องเรียน/งานซ่อมเป็นจำนวนทั้งหมดที่คุณมีสิทธิ์ดู ส่วนกิจกรรม Attendance คะแนน และการเงินตามปีที่เลือก</p>
<div class="dashboard-grid">
    @foreach ($cards as $card)
        <section class="dashboard-stat"><h2 style="font-size:1rem;margin:0">{{ $card['label'] }}</h2><span class="dashboard-value">{{ $card['value'] }}</span><a href="{{ $card['url'] }}">ดูรายการ{{ $card['label'] }}</a></section>
    @endforeach
</div>
<p>เรื่องร้องเรียนที่ยังดำเนินการ: {{ $openComplaints }} / งานซ่อมที่ยังดำเนินการ: {{ $openRepairs }}</p>
<section class="dashboard-section">
    <h2>ภาพรวมการเงิน</h2><p>นับเฉพาะรายการบันทึกแล้วที่คุณมีสิทธิ์ดูตามปีที่เลือก</p>
    <div class="dashboard-grid">
        <div class="dashboard-stat">รายรับทั้งหมด<span class="dashboard-value">{{ $totals['income'] }}</span>บาท</div>
        <div class="dashboard-stat">รายจ่ายทั้งหมด<span class="dashboard-value">{{ $totals['expense'] }}</span>บาท</div>
        <div class="dashboard-stat">ยอดคงเหลือ<span class="dashboard-value">{{ $totals['balance'] }}</span>บาท</div>
    </div>
    <a href="{{ $financeUrl }}">ดูรายการการเงิน</a>
</section>
