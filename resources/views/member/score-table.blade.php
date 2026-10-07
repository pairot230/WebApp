<div class="table-wrapper"><table><thead><tr><th>วันที่ (เวลาไทย)</th><th>ปี</th><th>กิจกรรม / เหตุผล</th><th>คะแนน</th>@if ($showCreator)<th>ผู้บันทึก</th>@endif</tr></thead><tbody>
    @forelse ($histories as $history)
        <tr><td>{{ $history->created_at->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td><td>{{ $history->academicYear->year }}</td><td>{{ $history->reason }}</td><td>{{ $history->score > 0 ? '+' : '' }}{{ $history->score }}</td>@if ($showCreator)<td>{{ $history->creator->name }}</td>@endif</tr>
    @empty
        <tr><td colspan="{{ $showCreator ? 5 : 4 }}">ยังไม่มีประวัติคะแนน</td></tr>
    @endforelse
</tbody></table></div>
{{ $histories->links() }}
