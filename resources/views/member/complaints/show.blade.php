@extends('layouts.app')
@section('title', 'ติดตามเรื่องร้องเรียน | KKU DORM')
@section('content')
    <h1>{{ $complaint->title }}</h1>
    @include('member.complaints.details')
    <a href="{{ route('member.complaints.index') }}">กลับรายการของฉัน</a>
@endsection
