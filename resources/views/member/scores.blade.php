@extends('layouts.app')
@section('content')
    <h1>คะแนนของฉัน</h1>
    <p>คะแนนรวมตามตัวกรอง: {{ $total }}</p>
    <form class="filters" method="GET"><select name="academic_year_id" aria-label="ปีการศึกษา"><option value="">ทุกปีการศึกษา</option>
        @foreach ($years as $year)<option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>{{ $year->year }}</option>@endforeach
    </select><button>ค้นหา</button></form>
    @include('member.score-table', ['showCreator' => false])
@endsection
