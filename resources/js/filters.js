/**
 * Pill-tab filtering for lists of cards.
 *
 * Markup:
 *   <div data-filter-scope>
 *     <button data-filter="all" aria-selected="true">All</button>
 *     <button data-filter="sunday" aria-selected="false">Sunday</button>
 *     <input data-filter-search>             (optional)
 *     <div data-filter-item data-tag="sunday" data-search="title text">…</div>
 *     <p data-filter-empty hidden>…</p>       (optional)
 *   </div>
 *
 * Add data-filter-param="topic" to the scope to preselect from a link like ?topic=Faith.
 */
for (const scope of document.querySelectorAll('[data-filter-scope]')) {
    const buttons = [...scope.querySelectorAll('[data-filter]')];
    const items = [...scope.querySelectorAll('[data-filter-item]')];
    const search = scope.querySelector('[data-filter-search]');
    const empty = scope.querySelector('[data-filter-empty]');
    let current = buttons.find((b) => b.getAttribute('aria-selected') === 'true')?.dataset.filter ?? 'all';

    const param = scope.dataset.filterParam;
    const fromUrl = param ? new URLSearchParams(location.search).get(param) : null;
    if (fromUrl && buttons.some((b) => b.dataset.filter === fromUrl)) {
        current = fromUrl;
        for (const b of buttons) b.setAttribute('aria-selected', String(b.dataset.filter === current));
    }

    const apply = () => {
        const query = search?.value.trim().toLowerCase() ?? '';
        let shown = 0;
        for (const item of items) {
            const matchesTag = current === 'all' || item.dataset.tag === current;
            const matchesQuery = !query || (item.dataset.search ?? item.textContent).toLowerCase().includes(query);
            item.hidden = !(matchesTag && matchesQuery);
            if (!item.hidden) shown++;
        }
        if (empty) empty.hidden = shown > 0;
    };

    for (const button of buttons) {
        button.addEventListener('click', () => {
            current = button.dataset.filter ?? 'all';
            for (const b of buttons) b.setAttribute('aria-selected', String(b === button));
            apply();
        });
    }
    search?.addEventListener('input', apply);
    apply();
}
