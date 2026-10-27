// Mobile navigation toggle in the site header.
const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.getElementById('mobile-nav');

if (toggle && menu) {
    const openIcon = toggle.querySelector('[data-icon-open]');
    const closeIcon = toggle.querySelector('[data-icon-close]');
    const setOpen = (open) => {
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        openIcon.hidden = open;
        closeIcon.hidden = !open;
    };

    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    // Close after following an in-page link such as /#prayer.
    menu.addEventListener('click', (e) => {
        if (e.target.closest('a')) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
}
