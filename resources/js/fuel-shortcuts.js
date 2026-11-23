export function bindFuelShortcuts(form) {
    const shortcuts = form.querySelector('[data-fuel-shortcuts]');
    if (!shortcuts) return;

    const product = form.elements.namedItem('product_id');
    const buttons = shortcuts.querySelectorAll('[data-quick-product]');
    const syncProduct = () => buttons.forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset.quickProduct === product.value));
    });
    buttons.forEach(button => button.addEventListener('click', () => {
        product.value = button.dataset.quickProduct;
        product.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    product.addEventListener('change', syncProduct);
    form.addEventListener('reset', () => queueMicrotask(syncProduct));
    syncProduct();
    shortcuts.hidden = false;
}
