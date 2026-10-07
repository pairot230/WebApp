const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/navigation.js'), 'utf8');

function setup(isMobile) {
    const element = () => ({ hidden: false, attributes: {}, listeners: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        focus() { this.focused = true; },
    });
    const toggle = element();
    const navigation = element();
    const table = element();
    const media = { matches: isMobile, addEventListener(name, callback) { this.change = callback; } };
    vm.runInNewContext(source, {
        document: { getElementById: id => id === 'menu-toggle' ? toggle : navigation, querySelectorAll: () => [table] },
        window: { matchMedia: () => media },
    });
    return { toggle, navigation, table, media };
}

test('mobile menu opens and closes without submitting a form', () => {
    const app = setup(true);
    assert.equal(app.navigation.hidden, true);
    assert.equal(app.toggle.hidden, false);
    app.toggle.listeners.click();
    assert.equal(app.navigation.hidden, false);
    assert.equal(app.toggle.attributes['aria-expanded'], 'true');
    app.toggle.listeners.click();
    assert.equal(app.navigation.hidden, true);
    assert.equal(app.toggle.attributes['aria-expanded'], 'false');
});

test('resizing between desktop and mobile keeps navigation reachable', () => {
    const app = setup(false);
    assert.equal(app.navigation.hidden, false);
    assert.equal(app.toggle.hidden, true);
    app.media.matches = true;
    app.media.change();
    assert.equal(app.navigation.hidden, true);
    assert.equal(app.toggle.hidden, false);
    app.media.matches = false;
    app.media.change();
    assert.equal(app.navigation.hidden, false);
    assert.equal(app.toggle.hidden, true);
});

test('Escape returns focus to the mobile toggle and tables are keyboard reachable', () => {
    const app = setup(true);
    app.toggle.listeners.click();
    app.navigation.listeners.keydown({ key: 'Escape' });
    assert.equal(app.navigation.hidden, true);
    assert.equal(app.toggle.focused, true);
    assert.equal(app.table.tabIndex, 0);
    assert.equal(app.table.attributes.role, 'region');
    assert.match(app.table.attributes['aria-label'], /ตารางข้อมูล/);
});
