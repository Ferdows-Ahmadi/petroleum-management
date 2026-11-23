import test from 'node:test';
import assert from 'node:assert/strict';
import { bindMovementCounterparty } from '../../resources/js/movement-counterparty.js';

function form(typeValue, renderedRole, context = {}) {
    const type = Object.assign(new EventTarget(), { value: typeValue });
    const deal = { value: context.dealId ?? '' };
    const scope = {
        dataset: context,
        querySelector: (selector) => selector === '[name="movement_type"]' ? type : deal,
    };
    const resets = [];
    bindMovementCounterparty(scope, renderedRole, (...args) => resets.push(args));
    return {
        deal, resets,
        switchTo(value) {
            type.value = value;
            type.dispatchEvent(new Event('change'));
        },
    };
}

test('opening a draft preserves its party, but switching in either direction requires a new selection', () => {
    const draft = form('SUPPLIER_PAYMENT', 'SUPPLIER');
    assert.deepEqual(draft.resets, []);
    draft.switchTo('CUSTOMER_RECEIPT');
    draft.switchTo('SUPPLIER_PAYMENT');
    assert.deepEqual(draft.resets, [['CUSTOMER', undefined], ['SUPPLIER', undefined]]);
});

test('only the currently allocated deal can supply a default for the new side', () => {
    const entry = form('CUSTOMER_RECEIPT', 'CUSTOMER', { dealId: '1', customerId: '4', supplierId: '9' });
    entry.switchTo('SUPPLIER_PAYMENT');
    entry.switchTo('CUSTOMER_RECEIPT');
    entry.deal.value = '';
    entry.switchTo('SUPPLIER_PAYMENT');
    assert.deepEqual(entry.resets, [['SUPPLIER', '9'], ['CUSTOMER', '4'], ['SUPPLIER', undefined]]);
});

test('other movements clear the commercial party, including on initial render', () => {
    const entry = form('OTHER_RECEIPT', '');
    entry.switchTo('CUSTOMER_RECEIPT');
    entry.switchTo('OTHER_PAYMENT');
    assert.deepEqual(entry.resets, [['', undefined], ['CUSTOMER', undefined], ['', undefined]]);
});
