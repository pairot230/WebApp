<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>เข้าสู่ระบบ | KKU DORM</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
    <main class="login-card">
        <p class="brand">KKU DORM</p>
        <h1>เข้าสู่ระบบด้วยบัญชี KKU</h1>
        <p class="description">สำหรับนักศึกษามหาวิทยาลัยขอนแก่น กรุณาใช้บัญชี KKU Mail</p>
        @if (session('success'))
            <p class="success" role="status">{{ session('success') }}</p>
        @endif
        <p id="login-error" class="error" role="alert" @if (! session('error') && $clientId) hidden @endif>
            {{ session('error') ?: ($clientId ? '' : 'ระบบยังไม่พร้อมให้เข้าสู่ระบบ กรุณาติดต่อผู้ดูแล') }}
        </p>
        <section id="google-login" data-client-id="{{ $clientId }}" data-nonce="{{ $nonce }}"
            data-endpoint="{{ route('auth.google') }}" aria-labelledby="google-label">
            <h2 id="google-label">เข้าสู่ระบบด้วย Google</h2>
            <div id="google-button">
                <button id="google-placeholder" class="google-placeholder" type="button" disabled>
                    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11c-.5 2.5-1.9 4.6-4.1 6v5h6.6c3.9-3.6 6.1-8.6 6.1-14.7z"/>
                        <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.9l-6.6-5c-1.8 1.2-4.1 1.9-6.9 1.9-5.3 0-9.8-3.6-11.4-8.4H5.8v5.2C9.2 39.5 16.1 44 24 44z"/>
                        <path fill="#FBBC05" d="M12.6 27.6c-.8-2.3-.8-4.9 0-7.2v-5.2H5.8a20 20 0 0 0 0 17.6z"/>
                        <path fill="#EA4335" d="M24 12c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 6 29.5 4 24 4 16.1 4 9.2 8.5 5.8 15.2l6.8 5.2C14.2 15.6 18.7 12 24 12z"/>
                    </svg>
                    เข้าสู่ระบบด้วย Google
                </button>
            </div>
            <p id="login-loading" role="status" aria-live="polite" @if (! $clientId) hidden @endif>กำลังโหลดปุ่มเข้าสู่ระบบ…</p>
        </section>
        <a id="login-retry" href="{{ route('login') }}" hidden>ลองใหม่</a>
        <noscript><p class="error">กรุณาเปิด JavaScript เพื่อเข้าสู่ระบบด้วย Google</p></noscript>
        <p class="footer">ระบบจัดการหอพักนักศึกษา มหาวิทยาลัยขอนแก่น</p>
    </main>
    <script src="{{ asset('js/google-login.js') }}" defer></script>
</body>
</html>
