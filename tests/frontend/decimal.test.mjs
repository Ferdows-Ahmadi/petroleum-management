import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tradeCents, tradePreviewCents, formatCents, netWeight } from '../../resources/js/decimal.js';

test('thesis quantities, amounts and profit use exact integer previews', () => {
    assert.equal(formatCents(tradeCents('10000', '1000')), '10,000.00 USD');
    assert.equal(formatCents(tradeCents('10000', '1100') - tradeCents('10000', '1000')), '1,000.00 USD');
    assert.equal(netWeight('15000', '5000'), '10000.000');
});
test('half cents round up like PHP BCMath, including large values', () => {
    assert.equal(formatCents(tradeCents('1', '1005')), '1.01 USD');
    assert.equal(formatCents(tradeCents('999999999999999', '1')), '1,000,000,000,000.00 USD');
    assert.equal(formatCents(-100n), '-1.00 USD');
});
test('malformed, scientific, incomplete or contradictory weights do not fabricate a preview', () => {
    assert.equal(tradeCents('1e3', '1000'), 0n);
    assert.equal(netWeight('15000', ''), '');
    assert.equal(netWeight('5000', '15000'), '');
});

test('unknown delivery or price stays unknown while an explicit zero rate remains valid', () => {
    assert.equal(tradePreviewCents('', '1100'), null);
    assert.equal(tradePreviewCents('1000', ''), null);
    assert.equal(tradePreviewCents(netWeight('3000', ''), '1100'), null);
    assert.equal(tradePreviewCents('1000', '0'), 0n);
    assert.equal(tradePreviewCents('10000', '1100'), 1100000n);
});
