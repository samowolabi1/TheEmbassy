// Joining a service online: while the stream is on screen, tell the server once a minute.
// After enough minutes the member is marked present. Opening a meeting link refreshes the status.

const status = document.querySelector('[data-join-status]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

if (status?.dataset.watchUrl) {
    const message = status.querySelector('[data-join-message]');
    let timer;

    const beat = async () => {
        clearTimeout(timer);
        if (document.hidden) return; // paused while the tab is in the background
        try {
            const res = await fetch(status.dataset.watchUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.present) {
                message.textContent = "✓ You're marked present online. Welcome!";
                message.classList.add('status-chip--ok');
                return;
            }
            if (data.closed) {
                message.textContent = 'Online attendance for this service has closed.';
                return;
            }
            const left = data.minutes_left;
            message.textContent = `Keep watching: you'll be marked present in about ${left} ${left === 1 ? 'minute' : 'minutes'}.`;
        } catch {
            // Offline for a moment: try again on the next beat.
        }
        timer = setTimeout(beat, 60000);
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) beat();
    });
    beat();
}

// After opening Zoom or Meet in a new tab, show the updated status here.
for (const form of document.querySelectorAll('.join-meeting')) {
    form.addEventListener('submit', () => setTimeout(() => location.reload(), 2500));
}
