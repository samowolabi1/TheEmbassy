// Place-a-notice form: keep the Pay button's total in step with the chosen package.
const total = document.querySelector('[data-notice-total]');

if (total) {
    const form = total.closest('form');
    const update = () => {
        const sum = [...form.querySelectorAll('input[data-price]:checked')].reduce((n, input) => n + Number(input.dataset.price), 0);
        total.textContent = sum.toLocaleString('en-NG');
    };
    form.addEventListener('change', update);
    update();
}
