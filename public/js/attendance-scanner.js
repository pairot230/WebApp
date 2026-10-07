(() => {
    'use strict';
    const root = document.getElementById('attendance-scanner');
    if (!root) return;
    const start = document.getElementById('start-camera');
    const stop = document.getElementById('stop-camera');
    const image = document.getElementById('qr-image');
    const status = document.getElementById('scan-status');
    const preview = document.getElementById('scan-preview');
    const member = document.getElementById('scan-member');
    const confirm = document.getElementById('confirm-check-in');
    const cancel = document.getElementById('cancel-check-in');
    let scanner;
    let running = false;
    let busy = false;
    let pendingToken = null;

    async function request(url, token) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf},
                body: JSON.stringify({qr_token: token}),
            });
            const data = await response.json();
            if (!response.ok) {
                const message = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                throw new Error(message || 'เช็กชื่อไม่สำเร็จ กรุณาลองอีกครั้ง');
            }
            return data;
        } finally {
            clearTimeout(timeout);
        }
    }

    async function scanned(token) {
        if (busy || pendingToken) return;
        busy = true;
        status.textContent = 'กำลังตรวจสอบสมาชิก…';
        try {
            const data = await request(root.dataset.previewUrl, token);
            pendingToken = token;
            member.textContent = `${data.student_id} / ${data.name}`;
            preview.hidden = false;
            status.textContent = 'ตรวจชื่อกับเจ้าของ QR แล้วกดยืนยันเช็กชื่อ';
        } catch (error) {
            status.textContent = error.name === 'AbortError' ? 'การเชื่อมต่อหมดเวลา กรุณาลองใหม่' : error.message;
        } finally {
            busy = false;
        }
    }

    start.addEventListener('click', async () => {
        if (busy || running) return;
        busy = true;
        start.disabled = true;
        try {
            if (typeof Html5Qrcode === 'undefined') throw new Error('โหลดเครื่องอ่าน QR ไม่สำเร็จ กรุณารีเฟรชหน้า');
            scanner ||= new Html5Qrcode('qr-reader');
            await scanner.start({facingMode: 'environment'}, {fps: 8, qrbox: {width: 220, height: 220}}, scanned);
            running = true;
            stop.disabled = false;
            image.disabled = true;
            status.textContent = 'กล้องพร้อม กรุณาสแกน QR สมาชิก';
        } catch (error) {
            start.disabled = false;
            status.textContent = 'เปิดกล้องไม่ได้ กรุณาอนุญาตกล้องและใช้ HTTPS หรือเลือกภาพ QR';
        } finally {
            busy = false;
        }
    });

    stop.addEventListener('click', async () => {
        if (busy || !running) return;
        busy = true;
        try {
            await scanner.stop();
            running = false;
            start.disabled = false;
            stop.disabled = true;
            image.disabled = false;
        } catch (error) {
            status.textContent = 'ปิดกล้องไม่สำเร็จ กรุณาลองใหม่';
        } finally {
            busy = false;
        }
    });

    image.addEventListener('change', async () => {
        if (busy || running || pendingToken || !image.files[0]) return;
        busy = true;
        try {
            scanner ||= new Html5Qrcode('qr-reader');
            const token = await scanner.scanFile(image.files[0], true);
            busy = false;
            await scanned(token);
        } catch (error) {
            status.textContent = 'อ่าน QR จากภาพไม่ได้ กรุณาเลือกภาพที่ชัดเจน';
        } finally {
            busy = false;
            image.value = '';
        }
    });

    confirm.addEventListener('click', async () => {
        if (busy || !pendingToken) return;
        busy = true;
        confirm.disabled = true;
        cancel.disabled = true;
        status.textContent = 'กำลังบันทึกเช็กชื่อ…';
        try {
            const data = await request(root.dataset.url, pendingToken);
            status.textContent = `${data.message}: ${data.name} / ${data.score} คะแนน`;
        } catch (error) {
            status.textContent = error.name === 'AbortError'
                ? 'การเชื่อมต่อหมดเวลา กรุณารีเฟรชรายชื่อเพื่อตรวจว่าบันทึกแล้วหรือยัง' : error.message;
        } finally {
            preview.hidden = true;
            pendingToken = null;
            confirm.disabled = false;
            cancel.disabled = false;
            // Pause callbacks briefly so the same visible QR is not immediately scanned again.
            setTimeout(() => { busy = false; }, 2500);
        }
    });

    cancel.addEventListener('click', () => {
        if (busy) return;
        preview.hidden = true;
        pendingToken = null;
        status.textContent = 'ยกเลิกแล้ว';
        busy = true;
        setTimeout(() => { busy = false; }, 2500);
    });
})();
