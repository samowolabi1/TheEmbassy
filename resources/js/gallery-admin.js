// Gallery uploads: drag and drop, a count of chosen photos, and an upload progress bar.

const form = document.querySelector('[data-photo-upload]');

if (form) {
    const input = form.querySelector('[data-photo-input]');
    const zone = form.querySelector('[data-dropzone]');
    const count = form.querySelector('[data-photo-count]');
    const progress = form.querySelector('[data-photo-progress]');

    const showCount = () => {
        const n = input.files.length;
        count.textContent = n ? `${n} photo${n === 1 ? '' : 's'} chosen` : '';
    };
    input.addEventListener('change', showCount);

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('is-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('is-over');
        input.files = e.dataTransfer.files;
        showCount();
    });

    // Send with a progress bar, then reload to show the new photos.
    form.addEventListener('submit', (e) => {
        if (!input.files.length) return;
        e.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        progress.hidden = false;
        progress.value = 0;
        button.disabled = true;
        count.textContent = 'Uploading…';
        xhr.upload.addEventListener('progress', (ev) => {
            if (ev.lengthComputable) progress.value = Math.round((ev.loaded / ev.total) * 100);
            if (progress.value === 100) count.textContent = 'Resizing photos…';
        });
        xhr.addEventListener('load', () => {
            let data = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                // Not JSON, e.g. the server's upload limit page.
            }
            if (xhr.status < 300 && data.redirect) {
                location.href = data.redirect;
                return;
            }
            const errors = Object.values(data.errors ?? {}).flat();
            count.textContent = errors[0] ?? (xhr.status === 413 ? 'Those photos are too big to send at once. Try fewer.' : 'Upload failed. Try fewer photos at a time.');
            button.disabled = false;
            progress.hidden = true;
        });
        xhr.addEventListener('error', () => {
            count.textContent = 'Upload failed. Check your connection and try again.';
            button.disabled = false;
            progress.hidden = true;
        });
        xhr.send(new FormData(form));
    });
}
