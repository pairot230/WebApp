@extends('layouts.app')
@section('title', 'การเงินที่เปิดเผย | KKU DORM')
@section('content')
    <h1>การเงินที่เปิดเผย</h1>
    @include('member.finance.summary', ['isAdmin' => false])
@endsection
