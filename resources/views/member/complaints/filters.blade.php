<form class="filters" method="GET">
    <input name="search" value="{{ request('search') }}" placeholder="ค้นหาชื่อเรื่อง" aria-label="ค้นหาชื่อเรื่อง" maxlength="100">
    <select name="status" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
        @foreach (App\Models\Complaint::STATUS_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit">ค้นหา</button><a href="{{ url()->current() }}">ล้างตัวกรอง</a>
</form>
