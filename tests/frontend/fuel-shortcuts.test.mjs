import test from 'node:test';
import assert from 'node:assert/strict';
import { bindFuelShortcuts } from '../../resources/js/fuel-shortcuts.js';

test('fuel shortcuts follow the catalog select, including restored and non-shortcut values', () => {
    const buttons = ['21', '34'].map(id => Object.assign(new EventTarget(), {
        dataset: { quickProduct: id },
        setAttribute(name, value) { this[name] = value; },
    }));
    const select = Object.assign(new EventTarget(), { value: '34' });
    const group = { hidden: true, querySelectorAll: () => buttons };
    const form = Object.assign(new EventTarget(), {
        elements: { namedItem: () => select }, querySelector: () => group,
    });
    bindFuelShortcuts(form);
    assert.equal(group.hidden, false);
    const states = () => buttons.map(button => button['aria-pressed']);
    assert.deepEqual(states(), ['false', 'true']);
    buttons[0].dispatchEvent(new Event('click'));
    assert.equal(select.value, '21');
    assert.deepEqual(states(), ['true', 'false']);
    select.value = '55';
    select.dispatchEvent(new Event('change'));
    assert.deepEqual(states(), ['false', 'false']);
    assert.equal(select.value, '55');
});
