<style>
    .dashboard-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr)); gap:16px; margin:20px 0; }
    .dashboard-stat { border:1px solid #e7e0da; border-radius:6px; padding:16px; }
    .dashboard-value { display:block; font-size:1.6rem; font-weight:bold; margin:8px 0; overflow-wrap:anywhere; }
    .dashboard-section { border-top:1px solid #e7e0da; margin-top:24px; padding-top:16px; }
</style>
<p>{{ auth()->user()->name }} / {{ auth()->user()->email }}</p>
<form method="GET" class="filters">
    <label>ปีการศึกษา<select name="academic_year_id"><option value="">ทุกปีการศึกษา</option>
        @foreach ($years as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
    </select></label>
    <button type="submit">แสดงข้อมูล</button><a href="{{ route('dashboard') }}">ล้างตัวกรอง</a>
</form>
