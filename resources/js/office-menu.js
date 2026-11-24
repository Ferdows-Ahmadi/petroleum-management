export function bindOfficeMenu(menuButton, menu, doc, mobile) {
    if (!menuButton || !menu) return;
    const setOpen = (open) => {
        menu.classList.toggle('open', open);
        menuButton.setAttribute('aria-expanded', String(open));
    };
    menuButton.addEventListener('click', () => setOpen(menuButton.getAttribute('aria-expanded') !== 'true'));
    doc.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && !menuButton.contains(event.target)) setOpen(false);
    });
    doc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            menuButton.focus();
        }
    });
    mobile.addEventListener('change', () => setOpen(false));
}
