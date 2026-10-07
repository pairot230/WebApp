@extends('layouts.app')
@section('content')
    <h1>คะแนน: {{ $member->name }}</h1>
    <p>คะแนนรวมตามตัวกรอง: {{ $total }}</p>
    <form class="filters" method="GET"><select name="academic_year_id" aria-label="ปีการศึกษา"><option value="">ทุกปีการศึกษา</option>
        @foreach ($years as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
    </select><button>ค้นหา</button></form>
    @include('member.score-table', ['showCreator' => true])
    @can('update', $member)
        <h2>ปรับคะแนน</h2>
        <form method="POST" action="{{ route('admin.users.scores.store', $member) }}">
            @csrf
            <label class="field">ปีการศึกษา<select name="academic_year_id" required>
                @foreach ($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
            </select></label>
            <label class="field">คะแนนที่เพิ่มหรือลด<input type="number" name="score" value="{{ old('score') }}" min="-10000" max="10000" required></label>
            <label class="field">เหตุผล<textarea name="reason" required maxlength="2000">{{ old('reason') }}</textarea></label>
            <p>ค่าบวกเพิ่มคะแนน ค่าลบลดคะแนน ทุกครั้งจะเพิ่มประวัติใหม่พร้อมผู้บันทึก</p>
            <button>บันทึกการปรับคะแนน</button>
        </form>
    @endcan
    <a href="{{ route('admin.users.show', $member) }}">กลับข้อมูลสมาชิก</a>
@endsection
