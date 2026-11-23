// Integer arithmetic for entry previews. PHP remains the posting authority.
export function scaled(value, scale) {
    const text = String(value ?? '');
    if (!/^\d+(?:\.\d+)?$/.test(text)) return 0n;
    const [whole, fraction = ''] = text.split('.');
    if (fraction.length > scale) return 0n;
    return BigInt(whole) * (10n ** BigInt(scale)) + BigInt(fraction.padEnd(scale, '0'));
}

export function tradeCents(quantity, rate) {
    // quantity scale 3, rate scale 4, tons divisor 1000, result scale 2.
    const raw = scaled(quantity, 3) * scaled(rate, 4);
    return (raw + 50000000n) / 100000000n;
}

export function tradePreviewCents(quantity, rate) {
    if (!/^\d+(?:\.\d{1,3})?$/.test(String(quantity ?? '')) || scaled(quantity, 3) <= 0n) return null;
    if (!/^\d+(?:\.\d{1,4})?$/.test(String(rate ?? ''))) return null;
    return tradeCents(quantity, rate);
}

export function formatCents(cents, currency = 'USD') {
    const negative = cents < 0n;
    const absolute = negative ? -cents : cents;
    const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${negative ? '-' : ''}${whole}.${(absolute % 100n).toString().padStart(2, '0')}${currency ? ` ${currency}` : ''}`;
}

export function formatWeight(value) {
    const raw = scaled(value, 3);
    const whole = (raw / 1000n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = (raw % 1000n).toString().padStart(3, '0').replace(/0+$/, '');
    return `${whole}${fraction ? `.${fraction}` : ''} kg`;
}

export function netWeight(first, second) {
    const result = scaled(first, 3) - scaled(second, 3);
    if (scaled(second, 3) <= 0n || result <= 0n) return '';
    return `${result / 1000n}.${(result % 1000n).toString().padStart(3, '0')}`;
}
