import test from 'node:test';
import assert from 'node:assert/strict';
import { preventImplicitEntrySubmit } from '../../resources/js/entry-keyboard.js';
import { partyKeyAction, partyMenuBounds } from '../../resources/js/party-combobox.js';

function preventsEnter(method, tagName, type, isComposing = false) {
    let prevented = false;
    preventImplicitEntrySubmit({
        key: 'Enter', target: { tagName, type, form: { method } }, isComposing,
        preventDefault() { prevented = true; },
    });
    return prevented;
}

test('Enter in transaction text, search, amount, and date inputs cannot submit a POST', () => {
    for (const type of ['text', 'search', 'number', 'date']) assert.ok(preventsEnter('post', 'INPUT', type));
});

test('GET search, multiline notes, explicit buttons, selects and IME composition retain Enter', () => {
    assert.equal(preventsEnter('get', 'INPUT', 'search'), false);
    for (const tag of ['TEXTAREA', 'BUTTON', 'SELECT']) assert.equal(preventsEnter('post', tag, 'text'), false);
    assert.equal(preventsEnter('post', 'INPUT', 'submit'), false);
    assert.equal(preventsEnter('post', 'INPUT', 'text', true), false);
});

test('Enter only chooses a highlighted party; arrows move it, Escape closes and Tab proceeds', () => {
    assert.deepEqual(partyKeyAction('Enter', 2, -1), { handled: true, select: -1 });
    assert.equal(partyKeyAction('ArrowDown', 2, -1).active, 0);
    assert.equal(partyKeyAction('ArrowUp', 2, -1).active, 1);
    assert.equal(partyKeyAction('ArrowUp', 0, -1).active, -1);
    assert.deepEqual(partyKeyAction('Enter', 2, 1), { handled: true, select: 1 });
    assert.deepEqual(partyKeyAction('Escape', 2, 1), { handled: true, close: true });
    assert.deepEqual(partyKeyAction('Tab', 2, 1), { handled: false, close: true });
});

test('menu follows the RTL input edge, flips above an onscreen keyboard and fits a small viewport', () => {
    const viewport = { left: 0, top: 0, width: 360, height: 620 };
    const input = { top: 120, bottom: 160, right: 350, width: 250 };
    assert.deepEqual(partyMenuBounds(input, viewport, 500), { top: 164, left: 100, width: 250, height: 238 });
    const keyboard = { ...viewport, height: 220 };
    assert.deepEqual(partyMenuBounds(input, keyboard, 500), { top: 6, left: 100, width: 250, height: 110 });
    const wide = { ...input, width: 420, right: 420 };
    assert.deepEqual(partyMenuBounds(wide, viewport, 40), { top: 164, left: 6, width: 348, height: 40 });
});
