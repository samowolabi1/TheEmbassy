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
