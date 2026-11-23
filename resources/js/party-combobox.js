const normalize = (value) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('fa');
const selectionHint = 'شخص را از فهرست انتخاب کنید یا برای ثبت شخص جدید «افزودن» را بزنید.';

export function partyMenuBounds(input, viewport, contentHeight) {
    const margin = 6;
    const gap = 4;
    const below = viewport.top + viewport.height - margin - input.bottom - gap;
    const above = input.top - gap - viewport.top - margin;
    const needed = Math.min(238, contentHeight);
    const flip = below < needed && above > below;
    const height = Math.max(0, Math.min(needed, flip ? above : below));
    const width = Math.min(input.width, Math.max(0, viewport.width - 2 * margin));
    const left = Math.max(viewport.left + margin, Math.min(input.right - width, viewport.left + viewport.width - margin - width));
    return { left, width, height, top: flip ? input.top - gap - height : input.bottom + gap };
}

export function partyKeyAction(key, count, activeIndex) {
    if (key === 'Enter') return { handled: true, select: activeIndex };
    if (key === 'Escape' || key === 'Tab') return { handled: key === 'Escape', close: true };
    if (key === 'ArrowDown') return { handled: true, active: Math.min(activeIndex + 1, count - 1) };
    if (key === 'ArrowUp') return { handled: true, active: count ? (activeIndex < 0 ? count - 1 : Math.max(0, activeIndex - 1)) : -1 };
    return { handled: false };
}

export function initializePartyCombobox(box) {
    const query = box.querySelector('[data-party-query]');
    const idInput = box.querySelector('[data-party-id]');
    const menu = box.querySelector('[data-party-menu]');
    const create = box.querySelector('[data-party-create]');
    const empty = box.querySelector('[data-party-empty]');
    const status = box.querySelector('[data-party-status]');
    let candidates = [];
    let activeIndex = -1;
    let revision = 0;
    let pending = false;
    let layoutFrame;

    function setActive(index) {
        const previous = candidates[activeIndex];
        previous?.classList.remove('active');
        previous?.setAttribute('aria-selected', 'false');
        activeIndex = index;
        const next = candidates[index];
        if (next) {
            next.classList.add('active');
            next.setAttribute('aria-selected', 'true');
            query.setAttribute('aria-activedescendant', next.id);
            const top = next.offsetTop;
            if (top < menu.scrollTop) menu.scrollTop = top;
            if (top + next.offsetHeight > menu.scrollTop + menu.clientHeight) menu.scrollTop = top + next.offsetHeight - menu.clientHeight;
        } else {
            query.removeAttribute('aria-activedescendant');
        }
    }

    function close() {
        cancelAnimationFrame(layoutFrame);
        setActive(-1);
        menu.hidePopover?.();
        menu.hidden = true;
        query.setAttribute('aria-expanded', 'false');
    }

    function position(event) {
        if (menu.hidden || event?.target === menu) return;
        const input = query.getBoundingClientRect();
        const visual = window.visualViewport;
        const viewport = {
            left: visual?.offsetLeft ?? 0, top: visual?.offsetTop ?? 0,
            width: visual?.width ?? document.documentElement.clientWidth,
            height: visual?.height ?? document.documentElement.clientHeight,
        };
        if (input.bottom <= viewport.top || input.top >= viewport.top + viewport.height || !input.width) return close();
        // Measure natural height after setting width, including any wrapped labels.
        menu.style.width = Math.min(input.width, viewport.width - 12) + 'px';
        menu.style.maxHeight = '';
        const bounds = partyMenuBounds(input, viewport, menu.scrollHeight + 2);
        menu.style.left = bounds.left + 'px';
        menu.style.top = bounds.top + 'px';
        menu.style.maxHeight = bounds.height + 'px';
    }

    function open() {
        setActive(-1);
        const needle = normalize(query.value);
        const options = [...menu.querySelectorAll('[data-party-option]')];
        candidates = options.filter((option) => {
            option.hidden = !normalize(option.dataset.search).includes(needle);
            return !option.hidden;
        });
        const exact = candidates.filter((option) => normalize(option.dataset.name) === needle);
        empty.hidden = candidates.length > 0;
        create.hidden = needle.length < 2 || exact.length > 0;
        if (!create.hidden) {
            create.querySelector('span').textContent = 'افزودن «' + query.value.trim() + '»';
            candidates.push(create);
        }
        if (exact.length > 1 && !idInput.value) status.textContent = 'چند شخص هم‌نام پیدا شد. بر اساس شماره تماس یا کد، شخص مورد نظر را انتخاب کنید.';
        menu.hidden = false;
        menu.showPopover?.();
        query.setAttribute('aria-expanded', 'true');
        position();
        // Enclosing deal fields and browser focus scrolling can move the input later in this event.
        cancelAnimationFrame(layoutFrame);
        layoutFrame = requestAnimationFrame(position);
    }

    async function choose(option) {
        if (pending) return;
        const version = ++revision;
        const role = box.dataset.role;
        const originalQuery = query.value;
        const payload = option === create ? { name: query.value.trim() } : { partner_id: option.dataset.id };
        pending = true;
        idInput.value = '';
        status.textContent = 'در حال ثبت…';
        status.className = 'combobox-status';
        close();
        try {
            const response = await fetch(box.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ ...payload, role }),
            });
            const body = await response.json();
            if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || 'ثبت شخص ممکن نشد.');
            if (option === create) document.dispatchEvent(new CustomEvent('party-added', { detail: body }));
            if (version !== revision || role !== box.dataset.role || query.disabled || query.value !== originalQuery || idInput.value) return;
            idInput.value = body.id;
            query.value = body.name;
            status.textContent = 'انتخاب شد' + (body.reference ? ' · ' + body.reference : '');
            status.className = 'combobox-status success';
            close();
        } catch (error) {
            if (version !== revision || role !== box.dataset.role || query.disabled) return;
            status.textContent = error.message;
            status.className = 'combobox-status error';
        } finally {
            if (version === revision) pending = false;
        }
    }

    document.addEventListener('party-added', ({ detail: party }) => {
        if ([...menu.querySelectorAll('[data-party-option]')].some((option) => option.dataset.id === String(party.id))) return;
        const option = document.createElement('button');
        option.type = 'button';
        option.tabIndex = -1;
        option.id = query.id + '_option_' + party.id;
        option.className = 'party-option';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        const details = [party.reference, party.phone].filter(Boolean).join(' · ');
        Object.assign(option.dataset, { partyOption: '', id: party.id, name: party.name, search: party.name + ' ' + details });
        option.append(document.createElement('span'), document.createElement('small'));
        option.firstElementChild.textContent = party.name;
        option.lastElementChild.textContent = details;
        empty.before(option);
        if (!menu.hidden) open();
    });

    query.addEventListener('focus', open);
    query.addEventListener('click', () => { if (menu.hidden && !pending) open(); });
    query.addEventListener('input', () => {
        revision++;
        pending = false;
        idInput.value = '';
        status.className = 'combobox-status';
        status.textContent = query.value.trim() ? selectionHint : '';
        open();
    });
    query.addEventListener('keydown', (event) => {
        if (event.isComposing || event.keyCode === 229) return;
        if (menu.hidden && ['ArrowDown', 'ArrowUp'].includes(event.key)) open();
        const action = partyKeyAction(event.key, candidates.length, activeIndex);
        if (action.handled) event.preventDefault();
        if (action.close) close();
        if ('active' in action) setActive(action.active);
        if (action.select >= 0 && !event.repeat && !menu.hidden) choose(candidates[action.select]);
    });
    menu.addEventListener('mousedown', (event) => event.preventDefault());
    menu.addEventListener('click', (event) => {
        const option = event.target.closest('button[role="option"]');
        if (option && !option.hidden) choose(option);
    });
    query.addEventListener('blur', close);
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
    window.visualViewport?.addEventListener('resize', position);
    window.visualViewport?.addEventListener('scroll', position);
    query.form.addEventListener('submit', (event) => {
        if (query.disabled || idInput.value || (!query.value.trim() && !pending)) return;
        event.preventDefault();
        query.setCustomValidity(pending ? 'انتخاب شخص در حال ثبت است.' : selectionHint);
        query.reportValidity();
        query.setCustomValidity('');
    });

    return function resetSelection(role, suggestedId) {
        // A request from a previous role must stay invalid even after switching back.
        revision++;
        pending = false;
        close();
        const option = suggestedId ? [...menu.querySelectorAll('[data-party-option]')].find((item) => item.dataset.id === suggestedId) : null;
        box.dataset.role = role;
        box.hidden = !role;
        idInput.disabled = query.disabled = !role;
        query.required = Boolean(role);
        idInput.value = option?.dataset.id ?? '';
        query.value = option?.dataset.name ?? '';
        query.setCustomValidity('');
        status.className = 'combobox-status';
        if (option) {
            status.textContent = 'از معامله انتخاب شد · ' + option.querySelector('small').textContent;
        } else if (!role) {
            status.textContent = '';
        } else {
            status.textContent = role === 'SUPPLIER' ? 'عرضه‌کننده را انتخاب کنید.' : 'مشتری را انتخاب کنید.';
        }
    };
}
