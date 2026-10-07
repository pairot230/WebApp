<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าใช้งานชั่วคราว | KKU DORM</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
    <main class="login-card">
        <p class="brand">KKU DORM</p>
        <h1>เลือกบัญชีเข้าใช้งาน</h1>
        <p class="description">โหมดชั่วคราวสำหรับเครื่องพัฒนา ไม่ต้องเข้าสู่ระบบด้วย Google</p>
        @if ($errors->any())
            <p class="error" role="alert">{{ $errors->first() }}</p>
        @endif
        @forelse ($members as $member)
            <form method="POST" action="{{ route('auth.temporary') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $member->email }}">
                <p>{{ $member->name }} — {{ ucfirst($member->role) }}<br>{{ $member->email }}</p>
                <button class="logout" type="submit">เข้าใช้งานบัญชีนี้</button>
            </form>
        @empty
            <p class="error">ยังไม่มีบัญชีสำหรับเข้าใช้งานชั่วคราว</p>
        @endforelse
    </main>
</body>
</html>
