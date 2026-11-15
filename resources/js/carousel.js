// Home page hero slider. The progress bar under the current slide is a CSS animation; when it
// finishes, the next slide shows. Hovering, focusing or pressing pause holds it. Swipe on phones,
// arrow keys when it has focus. With reduced motion it doesn't move by itself.
const carousel = document.querySelector('[data-carousel]');

if (carousel) {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    carousel.classList.add('is-ready');

    if (slides.length > 1) {
        const dots = [...carousel.querySelectorAll('[data-dot]')];
        const num = carousel.querySelector('[data-slide-num]');
        const pause = carousel.querySelector('[data-pause]');
        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let index = 0;
        let paused = still;

        const show = (n) => {
            index = (n + slides.length) % slides.length;
            slides.forEach((s, i) => {
                const on = i === index;
                s.classList.toggle('is-active', on);
                s.toggleAttribute('inert', !on);
                on ? s.removeAttribute('aria-hidden') : s.setAttribute('aria-hidden', 'true');
            });
            dots.forEach((d, i) => {
                d.classList.toggle('is-done', i < index);
                i === index ? d.setAttribute('aria-current', 'true') : d.removeAttribute('aria-current');
                // Restart the bar's fill from empty.
                const fill = d.querySelector('.hero2__step-fill');
                fill.style.animation = 'none';
                void fill.offsetWidth;
                fill.style.animation = '';
            });
            if (num) num.textContent = String(index + 1).padStart(2, '0');
        };
        const setPaused = (value) => {
            paused = value;
            carousel.classList.toggle('is-paused', paused);
            carousel.classList.toggle('is-manual', paused && still);
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? 'Play the slides' : 'Pause the slides');
        };

        // The current bar filled up: move on.
        carousel.addEventListener('animationend', (e) => {
            if (e.animationName === 'stepFill' && !paused) show(index + 1);
        });
        carousel.querySelector('[data-prev]').addEventListener('click', () => show(index - 1));
        carousel.querySelector('[data-next]').addEventListener('click', () => show(index + 1));
        dots.forEach((d, i) => d.addEventListener('click', () => show(i)));
        pause.addEventListener('click', () => setPaused(!paused));
        carousel.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') show(index + 1);
            if (e.key === 'ArrowLeft') show(index - 1);
        });

        // Swipe left or right on phones.
        let startX = null;
        let startY = 0;
        carousel.addEventListener('pointerdown', (e) => {
            if (e.pointerType !== 'mouse') {
                startX = e.clientX;
                startY = e.clientY;
            }
        });
        carousel.addEventListener('pointerup', (e) => {
            if (startX === null) return;
            const dx = e.clientX - startX;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(e.clientY - startY)) show(index + (dx < 0 ? 1 : -1));
            startX = null;
        });

        setPaused(paused);
        show(0);
    }
}
