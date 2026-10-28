// Album lightbox: click a photo to view it large, with previous/next, swipe, keyboard,
// download and a link to that photo. Without JavaScript the photos open as plain links.

const scope = document.querySelector('[data-gallery]');
const dialog = scope?.querySelector('[data-lightbox]');

if (scope && dialog && typeof dialog.showModal === 'function') {
    const items = [...scope.querySelectorAll('[data-gallery-item]')];
    const stage = dialog.querySelector('[data-lightbox-stage]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    const count = dialog.querySelector('[data-lightbox-count]');
    const download = dialog.querySelector('[data-lightbox-download]');
    const link = dialog.querySelector('[data-lightbox-link]');
    const prev = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    let index = 0;
    let opener = null;

    const show = (i) => {
        index = (i + items.length) % items.length;
        const item = items[index];
        const { kind, full, video, id } = item.dataset;
        stage.replaceChildren();

        if (kind === 'video') {
            const frame = document.createElement('iframe');
            frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(video)}?autoplay=1&rel=0`;
            frame.title = item.dataset.caption || 'Video';
            frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            frame.allowFullscreen = true;
            stage.append(frame);
            download.hidden = true;
        } else {
            const img = document.createElement('img');
            img.src = full;
            img.alt = item.querySelector('img')?.alt ?? '';
            stage.append(img);
            download.hidden = false;
            download.href = full;
        }

        caption.textContent = item.dataset.caption ?? '';
        count.textContent = `${index + 1} of ${items.length}`;
        const url = new URL(location.href);
        url.hash = `photo-${id}`;
        link.dataset.copy = url.href;
        history.replaceState(null, '', url);
        prev.hidden = next.hidden = items.length < 2;

        // Load the neighbours ahead of time.
        for (const n of [index + 1, index - 1]) {
            const other = items[(n + items.length) % items.length];
            if (other.dataset.full) new Image().src = other.dataset.full;
        }
    };

    const open = (i) => {
        opener = items[i];
        dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
        show(i);
    };

    const close = () => dialog.close();

    dialog.addEventListener('close', () => {
        stage.replaceChildren(); // stops a playing video
        document.documentElement.style.overflow = '';
        history.replaceState(null, '', location.pathname + location.search);
        opener?.focus();
    });

    items.forEach((item, i) => {
        item.addEventListener('click', (e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey) return; // let new-tab clicks through
            e.preventDefault();
            open(i);
        });
    });

    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', close);
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog || e.target === stage) close();
    });
    dialog.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });

    // Swipe left or right on phones.
    let startX = null;
    stage.addEventListener('touchstart', (e) => (startX = e.touches[0].clientX), { passive: true });
    stage.addEventListener('touchend', (e) => {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        startX = null;
        if (Math.abs(dx) > 50) show(dx < 0 ? index + 1 : index - 1);
    });

    // A shared link to one photo opens it straight away.
    const fromHash = () => {
        const i = items.findIndex((item) => location.hash === `#photo-${item.dataset.id}`);
        if (i >= 0 && !dialog.open) open(i);
    };
    window.addEventListener('hashchange', fromHash);
    fromHash();
}
