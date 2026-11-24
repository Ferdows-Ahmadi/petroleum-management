import test from 'node:test';
import assert from 'node:assert/strict';
import { bindOfficeMenu } from '../../resources/js/office-menu.js';

test('office menu exposes its state, restores keyboard focus, and closes on outside click or resize', () => {
    const button = new EventTarget();
    const menu = {};
    const document = new EventTarget();
    const mobile = new EventTarget();
    let expanded = 'false';
    let visible = false;
    let focused = false;
    button.getAttribute = () => expanded;
    button.setAttribute = (key, value) => { assert.equal(key, 'aria-expanded'); expanded = value; };
    button.contains = (target) => target === button;
    button.focus = () => { focused = true; };
    menu.contains = (target) => target === menu;
    menu.classList = { toggle: (name, value) => { assert.equal(name, 'open'); visible = value; } };
    bindOfficeMenu(button, menu, document, mobile);
    for (const reason of ['escape', 'outside', 'resize']) {
        button.dispatchEvent(new Event('click'));
        assert.equal(expanded, 'true');
        assert.equal(visible, true);
        if (reason === 'escape') {
            document.dispatchEvent(Object.assign(new Event('keydown'), { key: 'Escape' }));
            assert.equal(focused, true);
        } else if (reason === 'outside') {
            document.dispatchEvent(new Event('click'));
        } else {
            mobile.dispatchEvent(new Event('change'));
        }
        assert.equal(expanded, 'false');
        assert.equal(visible, false);
    }
});
