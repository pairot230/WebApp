const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/attendance-scanner.js'), 'utf8');

function setup(responses = []) {
    const elements = {};
    for (const id of ['attendance-scanner', 'start-camera', 'stop-camera', 'qr-image', 'scan-status', 'scan-preview', 'scan-member', 'confirm-check-in', 'cancel-check-in']) {
        elements[id] = {dataset: {}, hidden: true, disabled: false, files: [{}], listeners: {}, addEventListener(name, callback) {this.listeners[name] = callback;}};
    }
    elements['attendance-scanner'].dataset = {url: '/attendance', previewUrl: '/preview', csrf: 'session-csrf'};
    const requests = [];
    let onScan;
    let stopped = 0;
    class Scanner {
        async start(camera, config, callback) { onScan = callback; }
        async stop() { stopped++; }
        async scanFile() { return 'opaque-qr-token'; }
    }
    vm.runInNewContext(source, {
        document: {getElementById: id => elements[id]}, Html5Qrcode: Scanner, AbortController,
        setTimeout(callback, duration) { if (duration === 2500) callback(); return 1; }, clearTimeout() {},
        fetch: async (url, options) => {
            requests.push({url, options});
            const response = responses.shift();
            if (response instanceof Error) throw response;
            return {ok: response?.ok !== false, json: async () => response?.data || {name: 'Member', student_id: '001', score: 10, message: 'Success'}};
        },
    });
    return {elements, requests, scan: token => onScan(token), click: id => elements[id].listeners.click(), image: () => elements['qr-image'].listeners.change(), stopped: () => stopped};
}

test('scan previews identity and waits for explicit confirmation before recording attendance', async () => {
    const app = setup();
    await app.click('start-camera');
    await app.scan('token');
    assert.equal(app.requests.length, 1);
    assert.equal(app.requests[0].url, '/preview');
    assert.equal(app.elements['scan-member'].textContent, '001 / Member');
    assert.equal(app.elements['scan-preview'].hidden, false);
    await app.scan('same-frame');
    assert.equal(app.requests.length, 1);
    await app.click('confirm-check-in');
    assert.equal(app.requests[1].url, '/attendance');
    assert.deepEqual(JSON.parse(app.requests[1].options.body), {qr_token: 'token'});
    assert.equal(app.requests[1].options.headers['X-CSRF-TOKEN'], 'session-csrf');
    assert.equal(app.requests[1].options.credentials, 'same-origin');
    assert.match(app.elements['scan-status'].textContent, /10/);
    assert.equal(app.elements['scan-preview'].hidden, true);
});

test('image scanner can preview a decoded QR and cancellation does not check in', async () => {
    const app = setup();
    await app.image();
    assert.equal(app.requests.length, 1);
    assert.deepEqual(JSON.parse(app.requests[0].options.body), {qr_token: 'opaque-qr-token'});
    app.click('cancel-check-in');
    await app.click('confirm-check-in');
    assert.equal(app.requests.length, 1);
});

test('duplicate and invalid QR errors display backend message and can retry', async () => {
    const app = setup([{ok: false, data: {errors: {qr_token: ['Invalid QR']}}}, {}, {ok: false, data: {message: 'Duplicate attendance'}}]);
    await app.click('start-camera');
    await app.scan('forged');
    assert.equal(app.elements['scan-status'].textContent, 'Invalid QR');
    await app.scan('valid');
    await app.click('confirm-check-in');
    assert.equal(app.elements['scan-status'].textContent, 'Duplicate attendance');
    assert.equal(app.elements['confirm-check-in'].disabled, false);
});

test('camera controls stop the stream and network failure unlocks confirmation', async () => {
    const app = setup([{}, new Error('Network unavailable')]);
    await app.click('start-camera');
    assert.equal(app.elements['qr-image'].disabled, true);
    await app.scan('token');
    await app.click('confirm-check-in');
    assert.equal(app.elements['scan-status'].textContent, 'Network unavailable');
    assert.equal(app.elements['confirm-check-in'].disabled, false);
    await app.click('stop-camera');
    assert.equal(app.stopped(), 1);
    assert.equal(app.elements['qr-image'].disabled, false);
});

test('repeated confirmation while the request is pending sends only one write', async () => {
    const app = setup();
    await app.click('start-camera');
    await app.scan('token');
    const first = app.click('confirm-check-in');
    const second = app.click('confirm-check-in');
    await Promise.all([first, second]);
    assert.equal(app.requests.filter(request => request.url === '/attendance').length, 1);
});
