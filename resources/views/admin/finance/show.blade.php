@extends('layouts.app')
@section('title', 'รายละเอียดการเงิน | KKU DORM')
@section('content')
    <h1>{{ $finance->title }}</h1>
    <dl>
        <dt>ประเภท / ปีการศึกษา</dt><dd>{{ App\Models\FinancialTransaction::TYPE_LABELS[$finance->type] }} / {{ $finance->academicYear->year }}</dd>
        <dt>สมาชิก</dt><dd>{{ $finance->member?->name ?? '—' }}</dd>
        <dt>รายการประเภท</dt><dd>{{ $finance->type === 'income' ? (App\Models\FinancialTransaction::INCOME_CATEGORIES[$finance->category] ?? $finance->category) : $finance->category }}</dd>
        <dt>วันที่ / จำนวนเงิน</dt><dd>{{ $finance->transaction_date->format('d/m/Y') }} / {{ $finance->amount }} บาท</dd>
        <dt>รายละเอียด</dt><dd style="white-space:pre-wrap">{{ $finance->description ?? '—' }}</dd>
        <dt>สถานะ / เปิดเผย</dt><dd>{{ App\Models\FinancialTransaction::STATUS_LABELS[$finance->status] }} / {{ $finance->is_public ? 'เปิดเผย' : 'ไม่เปิดเผย' }}</dd>
        <dt>ผู้บันทึก</dt><dd>{{ $finance->creator->name }}</dd>
        @if ($finance->status === 'voided')
            <dt>เหตุผลยกเลิก</dt><dd style="white-space:pre-wrap">{{ $finance->void_reason }}</dd>
            <dt>ผู้ยกเลิก / เวลา (เวลาไทย)</dt><dd>{{ $finance->voidedBy->name }} / {{ $finance->voided_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</dd>
        @endif
    </dl>
    @if ($finance->status !== 'voided')
        <a href="{{ route('admin.finance.edit', $finance) }}">แก้ไขรายการ</a>
        <h2>ยกเลิกรายการ</h2>
        <form method="POST" action="{{ route('admin.finance.void', $finance) }}" onsubmit="return confirm('ยืนยันยกเลิกรายการนี้? ประวัติเดิมจะยังคงอยู่')">
            @csrf
            <label class="field">เหตุผลยกเลิก<textarea name="void_reason" required maxlength="2000">{{ old('void_reason') }}</textarea></label>
            <button type="submit">ยกเลิกรายการ</button>
        </form>
    @endif
    <a href="{{ route('admin.finance.index') }}">กลับรายการ</a>
@endsection
