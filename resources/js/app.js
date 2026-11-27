// Small enhancements; every page works without them.
import './menu';
import './carousel';
import './filters';
import './share';
import './countdown';
import './give';
import './check-in';
import './notices';
import './booking';
import './gallery';
import './app-shell';
import './community';
import './join';
import './fields';
import './dialog';
import './verses';

// Product photos: arrows, thumbnails and keyboard move the gallery; swiping works by itself.
document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const track = gallery.querySelector('[data-gallery-track]');
    const photos = [...track.children];
    const thumbs = [...gallery.querySelectorAll('[data-gallery-go]')];
    const prev = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    const current = () => Math.round(track.scrollLeft / track.clientWidth);
    const go = (i) => track.scrollTo({ left: Math.max(0, Math.min(photos.length - 1, i)) * track.clientWidth });
    const mark = () => {
        const i = current();
        thumbs.forEach((t, n) => t.setAttribute('aria-current', n === i ? 'true' : 'false'));
        if (prev) prev.disabled = i === 0;
        if (next) next.disabled = i === photos.length - 1;
    };
    thumbs.forEach((t) => t.addEventListener('click', () => go(Number(t.dataset.galleryGo))));
    prev?.addEventListener('click', () => go(current() - 1));
    next?.addEventListener('click', () => go(current() + 1));
    track.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') { e.preventDefault(); go(current() + 1); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(current() - 1); }
    });
    let t;
    track.addEventListener('scroll', () => { clearTimeout(t); t = setTimeout(mark, 60); }, { passive: true });
    mark();
});
