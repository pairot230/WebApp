@extends('layouts.app')
@section('title', 'งานแจ้งซ่อมของฉัน | KKU DORM')
@section('content')
    <h1>งานแจ้งซ่อมของฉัน</h1>
    @can('create', App\Models\RepairRequest::class)<a href="{{ route('member.repairs.create') }}">แจ้งซ่อม</a>@endcan
    @include('member.repairs.list', ['isAdmin' => false])
@endsection
