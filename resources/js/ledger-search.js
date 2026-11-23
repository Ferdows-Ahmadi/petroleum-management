// Search the rendered statement. Running balances retain their original row context.
function normalize(value) {
    return value.toLocaleLowerCase('fa')
        .replace(/[۰-۹]/g, digit => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 0x0660))
        .replace(/(?<=\d)[,٬](?=\d)/g, '')
        .replace(/٫/g, '.')
        .replace(/ك/g, 'ک')
        .replace(/ي/g, 'ی');
}

export function bindLedgerSearch(ledger) {
    const controls = ledger.querySelector('[data-ledger-search]');
    if (!controls) return;

    const input = controls.querySelector('input');
    const count = controls.querySelector('[data-ledger-count]');
    const rows = Array.from(ledger.querySelectorAll('.ledger-table tbody tr'), row => ({
        row,
        text: normalize(Array.from(row.cells, cell => cell.textContent).join(' ')),
    }));
    const filter = () => {
        const terms = normalize(input.value).trim().split(/\s+/);
        let matches = 0;
        for (const { row, text } of rows) {
            row.hidden = !terms.every(term => text.includes(term));
            if (!row.hidden) matches++;
        }
        count.textContent = matches === 0 ? 'ردیفی یافت نشد' : `${matches} از ${rows.length} ردیف`;
    };
    controls.hidden = false;
    input.addEventListener('input', filter);
    filter();
}
