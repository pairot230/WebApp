@extends('layouts.app')
@section('title', 'ติดตามงานแจ้งซ่อม | KKU DORM')
@section('content')
    <h1>ติดตามงานแจ้งซ่อม</h1>
    @include('member.repairs.details')
    <a href="{{ route('member.repairs.index') }}">กลับรายการของฉัน</a>
@endsection
