import test from 'node:test';
import assert from 'node:assert/strict';
import { bindLedgerSearch } from '../../resources/js/ledger-search.js';

test('ledger search matches Dari names, references and localized amounts without changing balances or order', () => {
    const rows = [
        ['PAY-12', 'کریم', '1,250.50', '3,000.00'],
        ['PAY-13', 'کریم', '250.00', '2,750.00'],
    ].map(values => ({ cells: values.map(textContent => ({ textContent })) }));
    const count = {};
    let onInput;
    const input = { value: '', addEventListener(event, handler) { assert.equal(event, 'input'); onInput = handler; } };
    const controls = { hidden: true, querySelector: selector => selector === 'input' ? input : count };
    bindLedgerSearch({ querySelector: () => controls, querySelectorAll: () => rows });
    assert.equal(controls.hidden, false);

    for (const [query, hidden] of [
        ['كريم ۱٬۲۵۰٫۵۰', [false, true]], ['pay-١٣', [true, false]],
        ['missing', [true, true]], ['', [false, false]],
    ]) {
        input.value = query;
        onInput();
        assert.deepEqual(rows.map(row => row.hidden), hidden);
        if (query === 'missing') assert.equal(count.textContent, 'ردیفی یافت نشد');
    }
    assert.equal(count.textContent, '2 از 2 ردیف');
    assert.deepEqual(rows.map(row => row.cells[3].textContent), ['3,000.00', '2,750.00']);
});
