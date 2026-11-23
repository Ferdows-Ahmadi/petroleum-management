document.querySelectorAll('[data-related-deal-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-deal-search]');
    const select = picker.querySelector('select');
    const more = picker.querySelector('[data-more-deals]');
    const status = picker.querySelector('[data-deal-status]');
    let timer;
    let currentRequest;
    let page = 1;

    const load = async (nextPage) => {
        currentRequest?.abort();
        const controller = new AbortController();
        currentRequest = controller;
        more.disabled = true;
        const url = new URL(picker.dataset.endpoint);
        url.searchParams.set('search', search.value.trim());
        url.searchParams.set('page', nextPage);
        if (picker.dataset.partner) url.searchParams.set('partner_id', picker.dataset.partner);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Search unavailable');
            const { options, has_more } = await response.json();
            if (controller.signal.aborted) return;
            if (nextPage === 1) {
                // A search changes available choices. Only an explicit selection changes the linked deal.
                const selected = select.selectedOptions[0];
                const selectedValue = select.value;
                select.replaceChildren(select.options[0], ...(selectedValue ? [selected] : []));
                select.value = selectedValue;
            }
            const known = new Set(Array.from(select.options, option => option.value));
            for (const item of options) {
                if (!known.has(String(item.id))) select.add(new Option(item.label, item.id));
            }
            page = nextPage;
            more.hidden = !has_more;
            status.textContent = options.length ? '' : 'معامله‌ای پیدا نشد.';
        } catch (error) {
            if (!controller.signal.aborted) status.textContent = 'جستجو انجام نشد. دوباره تلاش کنید.';
        } finally {
            if (currentRequest === controller) more.disabled = false;
        }
    };

    search.hidden = false;
    // The initial server-rendered options also work without JavaScript.
    more.hidden = picker.dataset.hasMore !== '1';
    search.addEventListener('input', () => {
        currentRequest?.abort();
        clearTimeout(timer);
        more.hidden = true;
        status.textContent = '';
        timer = setTimeout(() => load(1), 250);
    });
    more.addEventListener('click', () => load(page + 1));
});
