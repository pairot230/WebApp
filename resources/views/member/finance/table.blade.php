<div class="table-wrapper"><table><thead><tr><th>วันที่</th><th>ปี</th><th>รายการ / ประเภท</th><th>จำนวนเงิน (บาท)</th>@if ($isAdmin)<th>สมาชิก / ผู้บันทึก</th><th>สถานะ / เปิดเผย</th><th>จัดการ</th>@endif</tr></thead><tbody>
    @forelse ($records as $record)
        <tr><td>{{ $record->transaction_date->format('d/m/Y') }}</td><td>{{ $record->academicYear->year }}</td><td>{{ $record->title }}<br>{{ $record->type === 'income' ? (App\Models\FinancialTransaction::INCOME_CATEGORIES[$record->category] ?? $record->category) : $record->category }}</td><td>{{ $record->amount }}</td>
            @if ($isAdmin)
                <td>{{ $record->member?->name ?? '—' }} / {{ $record->creator->name }}</td><td>{{ App\Models\FinancialTransaction::STATUS_LABELS[$record->status] }} / {{ $record->is_public ? 'เปิดเผย' : 'ไม่เปิดเผย' }}</td><td><a href="{{ route('admin.finance.show', $record) }}">รายละเอียด</a></td>
            @endif
        </tr>
    @empty
        <tr><td colspan="{{ $isAdmin ? 7 : 4 }}">ไม่พบรายการ</td></tr>
    @endforelse
</tbody></table></div>
{{ $records->links() }}
