// Member check-in: get the phone's location before sending the form. The server decides whether
// it's close enough; nothing here can check someone in on its own.
for (const form of document.querySelectorAll('[data-geo-checkin]')) {
    const status = form.querySelector('[data-geo-status]');
    let located = false;

    form.addEventListener('submit', (e) => {
        if (located) return;
        e.preventDefault();
        if (!('geolocation' in navigator)) {
            status.textContent = "This browser can't share your location. Ask an usher to mark you present.";
            return;
        }
        status.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.elements.latitude.value = pos.coords.latitude;
                form.elements.longitude.value = pos.coords.longitude;
                form.elements.accuracy.value = Math.round(pos.coords.accuracy);
                located = true;
                form.requestSubmit();
            },
            (err) => {
                status.textContent = err.code === err.PERMISSION_DENIED
                    ? 'Location is blocked for this site. Allow it in your browser settings, then try again.'
                    : "We couldn't get your location. Turn on GPS and try again, or ask an usher.";
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });
}

// Settings: fill in the venue's coordinates from where the admin is standing.
const useLocation = document.querySelector('[data-use-location]');
if (useLocation) {
    useLocation.hidden = !('geolocation' in navigator);
    useLocation.addEventListener('click', () => {
        useLocation.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.querySelector('[name="church__checkin__latitude"]').value = pos.coords.latitude.toFixed(6);
                document.querySelector('[name="church__checkin__longitude"]').value = pos.coords.longitude.toFixed(6);
                useLocation.textContent = `Filled in (accurate to about ${Math.round(pos.coords.accuracy)} m). Save to keep it.`;
            },
            () => (useLocation.textContent = "Couldn't get your location. Allow location and try again."),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    });
}
