// Product page: show the order total on the Pay button as the quantity changes.
const form = document.querySelector('[data-market-buy]');

if (form) {
    const total = form.querySelector('[data-market-total]');
    const quantity = form.querySelector('select[name="quantity"]');
    const update = () => {
        total.textContent = `₦${((Number(form.dataset.price) * Number(quantity.value)) / 100).toLocaleString('en-NG')}`;
    };
    quantity.addEventListener('change', update);
    update();
}
