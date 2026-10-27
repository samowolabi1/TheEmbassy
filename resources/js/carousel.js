// Home page highlights carousel: rotates every 6 seconds unless paused or reduced motion is on.
const carousel = document.querySelector('[data-carousel]');

if (carousel) {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const dots = [...carousel.querySelectorAll('[data-dot]')];
    const num = carousel.querySelector('[data-slide-num]');
    const pause = carousel.querySelector('[data-pause]');
    let index = 0;
    let timer;
    let paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const show = (n) => {
        index = (n + slides.length) % slides.length;
        slides.forEach((s, i) => (s.hidden = i !== index));
        dots.forEach((d, i) => (i === index ? d.setAttribute('aria-current', 'true') : d.removeAttribute('aria-current')));
        num.textContent = String(index + 1).padStart(2, '0');
    };
    const restart = () => {
        clearInterval(timer);
        carousel.classList.toggle('is-paused', paused);
        pause.setAttribute('aria-pressed', String(paused));
        pause.textContent = paused ? 'Play' : 'Pause';
        if (!paused) timer = setInterval(() => show(index + 1), 6000);
    };
    const go = (n) => {
        show(n);
        restart();
    };

    carousel.querySelector('[data-prev]').addEventListener('click', () => go(index - 1));
    carousel.querySelector('[data-next]').addEventListener('click', () => go(index + 1));
    dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
    pause.addEventListener('click', () => {
        paused = !paused;
        restart();
    });
    // Don't rotate while someone is reading or using the controls.
    carousel.addEventListener('focusin', () => clearInterval(timer));
    carousel.addEventListener('focusout', restart);
    restart();
}
