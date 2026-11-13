// Share and copy buttons.

// [data-share]: native share sheet, falling back to copying the text and link.
for (const btn of document.querySelectorAll('[data-share]')) {
    btn.addEventListener('click', async () => {
        const text = btn.dataset.shareText ?? '';
        const label = btn.textContent;
        try {
            if (navigator.share) {
                await navigator.share({ text, url: location.href });
                return;
            }
            await navigator.clipboard.writeText(`${text}\n${location.href}`);
            btn.textContent = 'Copied';
            setTimeout(() => (btn.textContent = label), 2000);
        } catch {
            // Share sheet dismissed or clipboard blocked: nothing to do.
        }
    });
}

// [data-copy]: copies the given text (or the page address) and confirms on the button.
for (const btn of document.querySelectorAll('[data-copy]')) {
    btn.addEventListener('click', async () => {
        const text = btn.dataset.copy || location.href;
        const label = btn.dataset.copyLabel ?? (btn.getAttribute('aria-label') || btn.textContent);
        try {
            await navigator.clipboard.writeText(text);
            if (btn.hasAttribute('aria-label')) {
                btn.setAttribute('aria-label', 'Copied');
                setTimeout(() => btn.setAttribute('aria-label', label), 2000);
            } else {
                btn.textContent = 'Copied';
                setTimeout(() => (btn.textContent = label), 2000);
            }
        } catch {
            // Clipboard blocked: the text is still on screen to copy by hand.
        }
    });
}

// [data-share-href]: WhatsApp/X links that need the live page address.
const shareText = encodeURIComponent(`${document.querySelector('h1')?.textContent.trim() ?? ''} ${location.href}`);
for (const a of document.querySelectorAll('[data-share-href]')) {
    a.href = a.dataset.shareHref + shareText;
}

// [data-share-native]: the phone's own share sheet, with the picture when the phone can share
// files (so it lands in WhatsApp status or Instagram ready to post). Hidden where there's no
// share sheet, as on most computers.
for (const btn of document.querySelectorAll('[data-share-native]')) {
    if (!navigator.share) continue;
    btn.hidden = false;
    btn.addEventListener('click', async () => {
        const text = btn.dataset.shareText ?? '';
        const title = btn.dataset.shareTitle ?? document.title;
        try {
            if (btn.dataset.shareImage) {
                const blob = await (await fetch(btn.dataset.shareImage)).blob();
                const file = new File([blob], `${title.replace(/[^\w-]+/g, '-').toLowerCase()}.png`, { type: 'image/png' });
                if (navigator.canShare?.({ files: [file] })) {
                    await navigator.share({ files: [file], text, title });
                    return;
                }
            }
            await navigator.share({ text, title });
        } catch {
            // Share sheet dismissed: nothing to do.
        }
    });
}

// [data-copy-from]: copies the text of another element (e.g. a caption the seller edited).
for (const btn of document.querySelectorAll('[data-copy-from]')) {
    btn.addEventListener('click', async () => {
        const source = document.querySelector(btn.dataset.copyFrom);
        const label = btn.textContent;
        try {
            await navigator.clipboard.writeText(source?.value ?? source?.textContent ?? '');
            btn.textContent = 'Copied';
            setTimeout(() => (btn.textContent = label), 2000);
        } catch {
            source?.select?.();
        }
    });
}
