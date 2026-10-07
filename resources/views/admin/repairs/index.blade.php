@extends('layouts.app')
@section('title', 'จัดการงานซ่อม | KKU DORM')
@section('content')
    <h1>จัดการงานซ่อม</h1>
    @include('member.repairs.list', ['isAdmin' => true])
@endsection
