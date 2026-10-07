<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KKU DORM')</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/navigation.js') }}" defer></script>
</head>
<body>
    <main class="login-card content">
        <p class="brand">KKU DORM</p>
        <button id="menu-toggle" class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="true" hidden>เมนูหลัก</button>
        <nav id="main-navigation" class="main-navigation" aria-label="เมนูหลัก">
            <a href="{{ route('dashboard') }}">หน้าหลัก</a>
            <a href="{{ route('member.profile') }}">ข้อมูลของฉัน</a>
            <a href="{{ route('member.activities') }}">กิจกรรม</a>
            <a href="{{ route('member.qr') }}">QR ของฉัน</a>
            <a href="{{ route('member.scores') }}">คะแนนของฉัน</a>
            <a href="{{ route('member.complaints.index') }}">เรื่องร้องเรียนของฉัน</a>
            <a href="{{ route('member.repairs.index') }}">งานแจ้งซ่อมของฉัน</a>
            <a href="{{ route('member.finance') }}">การเงินที่เปิดเผย</a>
            @can('viewAny', App\Models\User::class)
                <a href="{{ route('admin.users.index') }}">สมาชิก</a>
                <a href="{{ route('admin.dorms.index') }}">หอพัก</a>
                <a href="{{ route('admin.buildings.index') }}">อาคาร</a>
                <a href="{{ route('admin.floors.index') }}">ชั้น</a>
                <a href="{{ route('admin.rooms.index') }}">ห้องพัก</a>
                <a href="{{ route('admin.activities.index') }}">จัดการกิจกรรม</a>
                <a href="{{ route('admin.complaints.index') }}">จัดการเรื่องร้องเรียน</a>
                <a href="{{ route('admin.repairs.index') }}">จัดการงานซ่อม</a>
                <a href="{{ route('admin.finance.index') }}">จัดการการเงิน</a>
            @endcan
            @if (auth()->user()->role === 'superadmin')
                <a href="{{ route('superadmin.admins.index') }}">จัดการสิทธิ์ Admin</a>
                <a href="{{ route('superadmin.audit.index') }}">Audit Log</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit">ออกจากระบบ</button>
            </form>
        </nav>
        @if (session('success')) <p class="success" role="status">{{ session('success') }}</p> @endif
        @if (session('error')) <p class="error" role="alert">{{ session('error') }}</p> @endif
        @if ($errors->any())
            <div class="error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</body>
</html>
