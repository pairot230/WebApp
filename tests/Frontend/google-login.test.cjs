const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/google-login.js'), 'utf8');

function setup(fetchResponse) {
    const elements = {};
    for (const id of ['google-login', 'login-loading', 'login-error', 'login-retry', 'google-button', 'google-placeholder']) {
        elements[id] = { hidden: false, dataset: {}, setAttribute() {} };
    }
    elements['google-login'].dataset = { clientId: 'test-client', nonce: 'session-nonce', endpoint: '/auth/google' };
    elements['login-error'].hidden = true;
    let callback;
    let script;
    let request;
    let redirected;
    const context = {
        document: {
            getElementById: id => elements[id],
            querySelector: () => ({ content: 'csrf-value' }),
            createElement: () => ({}),
            head: { appendChild: value => { script = value; } },
        },
        google: { accounts: { id: {
            initialize: config => { callback = config.callback; assert.equal(config.nonce, 'session-nonce'); },
            renderButton() {},
        } } },
        fetch: async (url, options) => { request = { url, options }; return fetchResponse(); },
        window: { location: { origin: 'http://localhost:8000', assign: value => { redirected = value; } } },
        URL, AbortController, setTimeout: () => 1, clearTimeout() {},
    };
    vm.runInNewContext(source, context);
    script.onload();
    return { elements, script, submit: token => callback({ credential: token }), request: () => request, redirected: () => redirected };
}

test('credential submission includes CSRF and uses same-origin redirect', async () => {
    const app = setup(async () => ({ ok: true, json: async () => ({ redirect: '/dashboard' }) }));
    await app.submit('sample-id-token');
    const request = app.request();
    assert.deepEqual(JSON.parse(request.options.body), { credential: 'sample-id-token' });
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-value');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(app.redirected(), 'http://localhost:8000/dashboard');
    assert.equal(app.elements['login-loading'].textContent, 'กำลังเข้าสู่ระบบ…');
});

test('domain errors show retry and do not redirect', async () => {
    const app = setup(async () => ({ ok: false, status: 403, json: async () => ({ message: 'กรุณาเข้าสู่ระบบด้วยบัญชี KKU เท่านั้น' }) }));
    await app.submit('sample-id-token');
    assert.equal(app.elements['login-error'].hidden, false);
    assert.equal(app.elements['login-retry'].hidden, false);
    assert.equal(app.elements['login-loading'].hidden, true);
    assert.equal(app.redirected(), undefined);
});

test('network errors show a safe error and retry link', async () => {
    const app = setup(async () => { throw new Error('network'); });
    await app.submit('sample-id-token');
    assert.equal(app.elements['login-error'].textContent, 'ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่');
    assert.equal(app.elements['login-retry'].hidden, false);
});

test('cross-origin redirects are rejected', async () => {
    const app = setup(async () => ({ ok: true, json: async () => ({ redirect: 'https://evil.example/' }) }));
    await app.submit('sample-id-token');
    assert.equal(app.redirected(), undefined);
    assert.equal(app.elements['login-error'].hidden, false);
});

test('Google script loading errors show retry', () => {
    const app = setup(async () => ({}));
    app.script.onerror();
    assert.equal(app.elements['login-error'].textContent, 'ไม่สามารถโหลด Google ได้ กรุณาลองใหม่');
    assert.equal(app.elements['login-retry'].hidden, false);
});
