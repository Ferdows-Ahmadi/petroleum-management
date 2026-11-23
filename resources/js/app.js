import { tradeCents, tradePreviewCents, formatCents, netWeight, scaled, formatWeight } from './decimal.js';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { preventImplicitEntrySubmit } from './entry-keyboard.js';
import { initializePartyCombobox } from './party-combobox.js';
import { bindMovementCounterparty } from './movement-counterparty.js';
import './related-deal-search.js';

const formatTrimmed = (value, maximumFractionDigits = 3) => Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits,
});

const menuButton = document.querySelector('[data-menu]');
const sidebar = document.querySelector('[data-sidebar]');

if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', (event) => {
        if (window.innerWidth < 992 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !menuButton.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });
}

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = button.closest('.password-control')?.querySelector('input');
    if (!input) return;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        button.setAttribute('aria-label', reveal ? 'پنهان‌کردن رمز عبور' : 'نمایش رمز عبور');
        button.querySelector('i')?.classList.toggle('bi-eye', !reveal);
        button.querySelector('i')?.classList.toggle('bi-eye-slash', reveal);
        input.focus();
    });
});

document.querySelector('.app-shell')?.addEventListener('keydown', preventImplicitEntrySubmit);
document.querySelectorAll('[data-party-combobox]').forEach((box) => {
    const resetSelection = initializePartyCombobox(box);
    if (box.hasAttribute('data-money-party-combo')) {
        bindMovementCounterparty(box.closest('[data-money-movement-fields]'), box.dataset.role, resetSelection);
    }
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-trade-form]').forEach((form) => {
    const list = form.querySelector('[data-item-list]');
    const template = form.querySelector('[data-item-template]');
    const currency = form.querySelector('[data-trade-currency]');
    let nextIndex = list?.querySelectorAll('[data-trade-item]').length ?? 0;

    const recalculate = () => {
        let total = 0n;
        form.querySelectorAll('[data-trade-item]').forEach((item) => {
            const amount = tradeCents(item.querySelector('[data-quantity]')?.value, item.querySelector('[data-rate]')?.value);
            const output = item.querySelector('[data-item-amount]');
            if (output) output.textContent = formatCents(amount, '');
            total += amount;
        });

        const totalOutput = form.querySelector('[data-trade-total]');
        if (totalOutput) totalOutput.textContent = formatCents(total, '');
        form.querySelectorAll('[data-currency-label], [data-total-currency]').forEach((label) => {
            label.textContent = currency?.value || 'USD';
        });
    };

    const renumber = () => {
        form.querySelectorAll('[data-trade-item]').forEach((item, index) => {
            const heading = item.querySelector('.trade-item-head strong');
            if (heading) heading.textContent = `#${index + 1}`;
        });
    };

    form.querySelector('[data-add-item]')?.addEventListener('click', () => {
        if (!list || !template || list.querySelectorAll('[data-trade-item]').length >= 20) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        renumber();
        recalculate();
    });

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-item]');
        if (!button || !list || list.querySelectorAll('[data-trade-item]').length <= 1) return;
        button.closest('[data-trade-item]')?.remove();
        renumber();
        recalculate();
    });

    form.addEventListener('input', recalculate);
    form.addEventListener('change', recalculate);
    recalculate();
});

document.querySelectorAll('[data-weighing-form]').forEach((form) => {
    const firstInput = form.querySelector('[data-first-weight]');
    const secondInput = form.querySelector('[data-second-weight]');
    const firstPreview = form.querySelector('[data-first-preview]');
    const secondPreview = form.querySelector('[data-second-preview]');
    const netPreview = form.querySelector('[data-net-preview]');

    const recalculate = () => {
        const first = firstInput?.value;
        const second = secondInput?.value;
        const net = netWeight(first, second);

        if (firstPreview) firstPreview.textContent = formatWeight(first);
        if (secondPreview) secondPreview.textContent = formatWeight(second);
        if (netPreview) netPreview.textContent = formatWeight(net);
    };

    form.addEventListener('input', recalculate);
    recalculate();
});

document.querySelectorAll('[data-office-sale-form]').forEach((form) => {
    const first = form.querySelector('[data-office-first]');
    const second = form.querySelector('[data-office-second]');
    const directNet = form.querySelector('[data-office-direct-net]');
    const mode = form.querySelector('[data-weighing-mode]');
    const rate = form.querySelector('[data-office-rate]');
    const netOutput = form.querySelector('[data-office-net]');
    const amountOutput = form.querySelector('[data-office-amount]');

    const recalculate = () => {
        const net = mode?.value === 'DIRECT_NET'
            ? directNet?.value : netWeight(first?.value, second?.value);
        if (netOutput) netOutput.textContent = formatWeight(net);
        if (amountOutput) amountOutput.textContent = formatCents(tradeCents(net, rate?.value));
    };

    form.addEventListener('input', recalculate);
    recalculate();
});

document.querySelectorAll('[data-mode-scope]').forEach((scope) => {
    const mode = scope.querySelector('[data-weighing-mode]');
    if (!mode) return;

    const setGroup = (selector, enabled) => {
        scope.querySelectorAll(selector).forEach((element) => {
            element.hidden = !enabled;
            const controls = element.matches('input, select, textarea') ? [element] : element.querySelectorAll('input, select, textarea');
            controls.forEach((control) => { control.disabled = !enabled; });
        });
    };
    const synchronize = () => {
        setGroup('[data-direct-fields]', mode.value === 'DIRECT_NET');
        setGroup('[data-two-stage-fields]', mode.value === 'TWO_STAGE');
        scope.dispatchEvent(new Event('input', { bubbles: true }));
    };
    mode.addEventListener('change', synchronize);
    synchronize();
});

document.querySelectorAll('[data-partner-form]').forEach((form) => {
    const name = form.querySelector('[data-partner-name]');
    const phoneField = form.querySelector('[data-new-partner-field]');
    if (!name || !phoneField) return;

    const values = new Set([...form.querySelectorAll(`datalist#${name.getAttribute('list')} option`)].map((option) => option.value.trim().toLocaleLowerCase()));
    const synchronize = () => {
        const isNew = name.value.trim() !== '' && !values.has(name.value.trim().toLocaleLowerCase());
        phoneField.hidden = !isNew;
        phoneField.querySelectorAll('input').forEach((input) => { input.disabled = !isNew; });
        if (isNew) phoneField.closest('details')?.setAttribute('open', '');
    };
    name.addEventListener('input', synchronize);
    synchronize();
});

document.querySelectorAll('[data-account-partner-selector]').forEach((form) => {
    const query = form.querySelector('[data-account-partner-query]');
    const id = form.querySelector('[data-account-partner-id]');
    const options = [...form.querySelectorAll('datalist option')];
    const synchronize = () => {
        const match = options.find((option) => option.value.trim().toLocaleLowerCase() === query.value.trim().toLocaleLowerCase());
        id.value = match?.dataset.id || '';
    };
    query.addEventListener('input', synchronize);
    synchronize();
});

document.querySelectorAll('[data-office-purchase-form]').forEach((form) => {
    const quantity = form.querySelector('[data-quantity]');
    const rate = form.querySelector('[data-rate]');
    const total = form.querySelector('[data-trade-total]');
    const recalculate = () => {
        if (total) total.textContent = formatCents(tradeCents(quantity?.value, rate?.value), '');
    };
    form.addEventListener('input', recalculate);
    recalculate();
});

document.querySelectorAll('[data-deal-form]').forEach((form) => {
    const sourceType = form.querySelector('[data-simple-source-type]');
    const destinationType = form.querySelector('[data-simple-destination-type]');
    const reveal = (side, type) => {
        const stored = type?.value === 'STORAGE';
        form.querySelectorAll('[data-simple-' + side + '-party], [data-simple-' + side + '-rate]').forEach((group) => {
            group.hidden = stored;
            group.querySelectorAll('input, select').forEach((input) => { input.disabled = stored; });
        });
        form.querySelectorAll('[data-simple-' + side + '-warehouse]').forEach((group) => {
            group.hidden = !stored;
            group.querySelectorAll('input, select').forEach((input) => { input.disabled = !stored; });
        });
    };
    const sourceQuantity = form.querySelector('[data-deal-source-quantity]');
    const sourceRate = form.querySelector('[data-deal-source-rate]');
    const destinationQuantity = form.querySelector('[data-deal-final-quantity]');
    const destinationRate = form.querySelector('[data-deal-destination-rate]');
    const mode = form.querySelector('[data-weighing-mode]');
    const directNet = form.querySelector('[data-deal-net-weight]');
    const first = form.querySelector('[data-deal-first-weight]');
    const second = form.querySelector('[data-deal-second-weight]');

    const recalculate = () => {
        reveal('source', sourceType);
        reveal('destination', destinationType);
        const sourceAmount = sourceType?.value === 'STORAGE' ? null : tradePreviewCents(sourceQuantity?.value, sourceRate?.value);
        let delivered = destinationQuantity?.value || '';
        if (mode?.value === 'DIRECT_NET' && scaled(directNet?.value, 3) > 0n) delivered = directNet.value;
        if (mode?.value === 'TWO_STAGE') delivered = netWeight(first?.value, second?.value);
        const destinationAmount = destinationType?.value === 'STORAGE' ? null : tradePreviewCents(delivered, destinationRate?.value);
        const money = formatCents;
        const sourceOutput = form.querySelector('[data-deal-source-amount]');
        const destinationOutput = form.querySelector('[data-deal-destination-amount]');
        const marginOutput = form.querySelector('[data-deal-margin]');
        if (sourceOutput) sourceOutput.textContent = sourceAmount === null ? '—' : money(sourceAmount);
        if (destinationOutput) destinationOutput.textContent = destinationAmount === null ? '—' : money(destinationAmount);
        if (marginOutput) marginOutput.textContent = sourceAmount === null || destinationAmount === null ? '—' : money(destinationAmount - sourceAmount);
    };

    form.addEventListener('input', recalculate);
    form.addEventListener('change', recalculate);
    recalculate();
});

document.querySelectorAll('[data-money-form]').forEach((form) => {
    const account = form.querySelector('[data-account-currency]');
    const currencyInput = form.querySelector('[data-currency-input]');
    const currencyDisplay = form.querySelector('[data-currency-display]');
    const synchronizeCurrency = () => {
        const currency = account?.selectedOptions[0]?.dataset.currency || 'USD';
        if (currencyInput) currencyInput.value = currency;
        if (currencyDisplay) currencyDisplay.textContent = currency;
    };
    account?.addEventListener('change', synchronizeCurrency);
    synchronizeCurrency();
});

document.querySelectorAll('[data-money-movement-fields]').forEach((scope) => {
    const route = scope.querySelector('[data-money-route]');
    const sarafField = scope.querySelector('[data-saraf-field]');
    const accountField = scope.querySelector('[data-account-field]');
    const toggle = (container, enabled) => {
        if (!container) return;
        container.hidden = !enabled;
        container.querySelectorAll('select, input').forEach((control) => {
            control.disabled = !enabled;
            if (!enabled) control.value = '';
        });
    };
    const synchronize = () => {
        toggle(sarafField, route?.value === 'saraf');
        toggle(accountField, route?.value === 'account');
    };
    route?.addEventListener('change', synchronize);
    synchronize();
});

document.querySelectorAll('form').forEach((form) => {
    if (form.matches('[data-deal-form]')) return;
    const type = form.querySelector('[name="source_type"], [name="destination_type"]');
    if (!type) return;
    const synchronize = () => {
        const stored = type.value === 'STORAGE';
        ['supplier_id', 'customer_id', 'warehouse_id', 'rate_per_ton'].forEach((name) => {
            const control = form.querySelector(`[name="${name}"]`);
            if (!control) return;
            const enabled = name === 'warehouse_id' ? stored : !stored;
            control.closest('.field').hidden = !enabled;
            control.disabled = !enabled;
        });
    };
    type.addEventListener('change', synchronize);
    synchronize();
});

document.querySelectorAll('[data-journal-context-form]').forEach((form) => {
    const context = form.querySelector('[data-journal-context]');
    const account = form.querySelector('[data-journal-account]');
    const saraf = form.querySelector('[data-journal-saraf]');
    const synchronize = () => {
        if (account) account.hidden = context?.value !== 'account';
        if (saraf) saraf.hidden = context?.value !== 'saraf';
    };
    context?.addEventListener('change', synchronize);
    synchronize();
});

document.querySelectorAll('[data-toggle-account-edit]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = document.querySelector('[data-account-edit-form]');
        if (!form) return;
        form.hidden = !form.hidden;
        const trigger = document.querySelector('[aria-controls="account-edit"]');
        trigger?.setAttribute('aria-expanded', String(!form.hidden));
        if (form.hidden) trigger?.focus();
        else form.querySelector('[name="name"]')?.focus();
    });
});
