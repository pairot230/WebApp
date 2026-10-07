@extends('layouts.app')
@section('title', 'บันทึกการเงิน | KKU DORM')
@section('content')
    <h1>{{ $finance->exists ? 'แก้ไข' : 'เพิ่ม' }}{{ App\Models\FinancialTransaction::TYPE_LABELS[$finance->type] }}</h1>
    <form method="POST" action="{{ $finance->exists ? route('admin.finance.update', $finance) : route('admin.finance.store') }}" onsubmit="const button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='กำลังบันทึก…';">
        @csrf
        @if ($finance->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $finance->type }}">
        <label class="field">ปีการศึกษา<select name="academic_year_id" required @disabled($finance->exists)>
            @foreach ($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $finance->academic_year_id) == $year->id)>{{ $year->year }}</option>@endforeach
        </select></label>
        @if ($finance->exists)<input type="hidden" name="academic_year_id" value="{{ $finance->academic_year_id }}">@endif
        @if ($finance->type === 'income')
            @if ($finance->exists)
                <p>สมาชิก: {{ $finance->member->name }} / {{ $finance->member->student_id ?? '—' }}</p>
                <input type="hidden" name="user_id" value="{{ $finance->user_id }}">
            @else
                <label class="field">สมาชิก<select name="user_id" required><option value="">เลือกสมาชิก</option>
                    @foreach ($members as $member)<option value="{{ $member->id }}" @selected(old('user_id') == $member->id)>{{ $member->name }} / {{ $member->student_id ?? '—' }}</option>@endforeach
                </select></label>
            @endif
            <label class="field">ประเภทรายรับ<select name="category" required @disabled($finance->exists)>
                @foreach (App\Models\FinancialTransaction::INCOME_CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category', $finance->category) === $value)>{{ $label }}</option>@endforeach
            </select></label>
            @if ($finance->exists)<input type="hidden" name="category" value="{{ $finance->category }}">@endif
            <p>ค่าส่วนกลางบันทึกได้หนึ่งรายการต่อสมาชิกต่อปีการศึกษา</p>
        @else
            <label class="field">ประเภทค่าใช้จ่าย<input name="category" value="{{ old('category', $finance->category) }}" required maxlength="100" placeholder="เช่น ซ่อมบำรุง"></label>
        @endif
        <label class="field">วันที่<input type="date" name="transaction_date" value="{{ old('transaction_date', $finance->transaction_date?->format('Y-m-d') ?? now('Asia/Bangkok')->toDateString()) }}" required></label>
        <label class="field">รายการ<input name="title" value="{{ old('title', $finance->title) }}" required maxlength="255"></label>
        <label class="field">จำนวนเงิน (บาท)<input type="number" name="amount" value="{{ old('amount', $finance->amount) }}" required min="0.01" max="9999999999.99" step="0.01"></label>
        <label class="field">รายละเอียด<textarea name="description" rows="4" maxlength="10000">{{ old('description', $finance->description) }}</textarea></label>
        <label class="field">สถานะ<select name="status" required>
            @foreach (['pending' => 'รอยืนยัน', 'posted' => 'บันทึกแล้ว'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $finance->status) === $value) @disabled($finance->exists && $finance->status === 'posted' && $value === 'pending')>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field">เปิดเผยรายการให้สมาชิก<select name="is_public"><option value="0" @selected(! old('is_public', $finance->is_public))>ไม่เปิดเผย</option><option value="1" @selected(old('is_public', $finance->is_public))>เปิดเผย</option></select></label>
        <p>รายการที่บันทึกแล้วนับในยอดรวม ทุกการแก้ไขเก็บประวัติ ผู้บันทึกมาจากบัญชีที่เข้าสู่ระบบ</p>
        <button type="submit">บันทึกข้อมูล</button><a href="{{ route('admin.finance.index') }}">กลับรายการ</a>
    </form>
@endsection
