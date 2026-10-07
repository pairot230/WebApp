<form method="GET" class="filters">
    <label>ปีการศึกษา<select name="academic_year_id"><option value="">ทุกปีการศึกษา</option>
        @foreach ($years as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
    </select></label>
    @if ($isAdmin)
        <input name="search" value="{{ request('search') }}" placeholder="ค้นหารายการ" aria-label="ค้นหารายการ" maxlength="100">
        <select name="status" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
            @foreach (App\Models\FinancialTransaction::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
        </select>
    @endif
    <button>ค้นหา / กรอง</button><a href="{{ url()->current() }}">ล้างตัวกรอง</a>
</form>
<dl>
    <dt>รายรับทั้งหมด</dt><dd>{{ $totals['income'] }} บาท</dd>
    <dt>รายจ่ายทั้งหมด</dt><dd>{{ $totals['expense'] }} บาท</dd>
    <dt>ยอดคงเหลือ</dt><dd>{{ $totals['balance'] }} บาท</dd>
</dl>
<p>{{ $isAdmin ? 'ยอดรวมตามปีที่เลือกนับเฉพาะรายการบันทึกแล้วที่คุณมีสิทธิ์ดู การค้นหาและสถานะกรองเฉพาะรายการด้านล่าง' : 'ยอดรวมนี้นับเฉพาะรายการที่เปิดเผยและบันทึกแล้วตามปีที่เลือก' }}</p>
<h2>รายการรายรับ</h2>
@include('member.finance.table', ['records' => $incomes])
<h2>รายการรายจ่าย</h2>
@include('member.finance.table', ['records' => $expenses])
