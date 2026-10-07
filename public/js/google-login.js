(() => {
    const container = document.getElementById('google-login');
    const loading = document.getElementById('login-loading');
    const error = document.getElementById('login-error');
    const retry = document.getElementById('login-retry');
    const button = document.getElementById('google-button');
    if (!container || !container.dataset.clientId) return;

    let submitting = false;
    const showError = (message) => {
        loading.hidden = true;
        error.textContent = message;
        error.hidden = false;
        retry.hidden = false;
        button.hidden = true;
        container.setAttribute('aria-busy', 'false');
    };

    const submitCredential = async (response) => {
        if (submitting) return;
        submitting = true;
        error.hidden = true;
        button.hidden = true;
        loading.textContent = 'กำลังเข้าสู่ระบบ…';
        loading.hidden = false;
        container.setAttribute('aria-busy', 'true');
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 20000);
        try {
            const result = await fetch(container.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ credential: response.credential }),
                signal: abort.signal,
            });
            const data = await result.json().catch(() => ({}));
            if (!result.ok) {
                const message = result.status === 419 ? 'คำขอเข้าสู่ระบบหมดอายุ กรุณาลองใหม่'
                    : result.status === 429 ? 'เข้าสู่ระบบบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่'
                    : data.message || 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่';
                showError(message);
                return;
            }
            const redirect = new URL(data.redirect, window.location.origin);
            if (redirect.origin !== window.location.origin) throw new Error('Invalid redirect');
            window.location.assign(redirect.href);
        } catch {
            showError('ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่');
        } finally {
            clearTimeout(timer);
        }
    };

    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client?hl=th';
    script.async = true;
    const loadTimer = setTimeout(() => showError('ไม่สามารถโหลด Google ได้ กรุณาลองใหม่'), 15000);
    script.onerror = () => {
        clearTimeout(loadTimer);
        showError('ไม่สามารถโหลด Google ได้ กรุณาลองใหม่');
    };
    script.onload = () => {
        clearTimeout(loadTimer);
        try {
            google.accounts.id.initialize({
                client_id: container.dataset.clientId,
                nonce: container.dataset.nonce,
                callback: submitCredential,
                auto_select: false,
            });
            document.getElementById('google-placeholder').hidden = true;
            google.accounts.id.renderButton(button, {
                type: 'standard', theme: 'outline', size: 'large', text: 'signin_with', locale: 'th',
            });
            loading.hidden = true;
        } catch {
            showError('ไม่สามารถโหลดปุ่มเข้าสู่ระบบได้ กรุณาลองใหม่');
        }
    };
    document.head.appendChild(script);
})();
