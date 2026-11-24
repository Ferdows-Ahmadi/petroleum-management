import test from 'node:test';
import assert from 'node:assert/strict';
import { initializePartyCombobox } from '../../resources/js/party-combobox.js';

test('selecting an existing general contact posts its ID and the requested role, then keeps the returned ID', async (t) => {
    const name = 'دفتر خیالی باران';
    const option = { hidden: false, dataset: { id: '41', name } };
    const menu = new EventTarget();
    const query = Object.assign(new EventTarget(), {
        value: name, form: new EventTarget(), setAttribute() {}, removeAttribute() {},
    });
    const id = { value: '' };
    const fields = { query, id, menu, status: {}, empty: {}, create: {} };
    const box = {
        dataset: { role: 'SUPPLIER', endpoint: '/inline/parties' },
        querySelector(selector) { return fields[selector.slice(12, -1)]; },
    };
    const doc = Object.assign(new EventTarget(), { querySelector: () => ({ content: 'test-token' }) });
    t.mock.method(globalThis, 'fetch', async (url, request) => {
        assert.equal(url, box.dataset.endpoint);
        assert.deepEqual(JSON.parse(request.body), { partner_id: '41', role: 'SUPPLIER' });
        return { ok: true, json: async () => ({ id: 41, name, roles: ['PARTNER', 'SUPPLIER'] }) };
    });
    for (const [key, value] of Object.entries({ window: new EventTarget(), document: doc, cancelAnimationFrame() {} })) {
        Object.defineProperty(globalThis, key, { configurable: true, value });
        t.after(() => { delete globalThis[key]; });
    }
    initializePartyCombobox(box);
    const clicked = new Event('click');
    Object.defineProperty(clicked, 'target', { value: { closest: () => option } });
    menu.dispatchEvent(clicked);
    await new Promise(setImmediate);
    assert.equal(fetch.mock.callCount(), 1);
    assert.equal(String(id.value), '41');
    assert.equal(query.value, name);
});
