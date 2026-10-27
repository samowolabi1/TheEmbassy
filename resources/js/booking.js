// Event booking form: show the total on the button as tickets are chosen.
const form = document.querySelector('[data-booking]');

if (form) {
    const label = form.querySelector('[data-booking-label]');
    const original = label.textContent;
    const update = () => {
        let count = 0;
        let total = 0;
        for (const select of form.querySelectorAll('select[data-price]')) {
            count += Number(select.value);
            total += Number(select.value) * Number(select.dataset.price);
        }
        if (!count) label.textContent = original;
        else if (!total) label.textContent = `Book ${count} free ${count === 1 ? 'place' : 'places'}`;
        else label.textContent = `Pay ₦${(total / 100).toLocaleString('en-NG')}`;
    };
    form.addEventListener('change', update);
    update();
}
