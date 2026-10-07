@extends('layouts.app')
@section('title', 'จัดการการเงิน | KKU DORM')
@section('content')
    <h1>จัดการการเงิน</h1>
    @can('create', App\Models\FinancialTransaction::class)
        <div class="actions"><a href="{{ route('admin.finance.create', ['type' => 'income']) }}">เพิ่มรายรับ</a><a href="{{ route('admin.finance.create', ['type' => 'expense']) }}">เพิ่มรายจ่าย</a></div>
    @endcan
    @include('member.finance.summary', ['isAdmin' => true])
@endsection
