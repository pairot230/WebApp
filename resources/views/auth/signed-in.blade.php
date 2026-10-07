@extends('layouts.app')
@section('title', 'เข้าสู่ระบบสำเร็จ | KKU DORM')
@section('content')
    <h1>เข้าสู่ระบบสำเร็จ</h1>
    <p>{{ auth()->user()->name }}</p>
    <p class="description">{{ auth()->user()->email }}</p>
@endsection
