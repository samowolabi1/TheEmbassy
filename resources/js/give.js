// Give page form: amount presets, live button label and checks before going to Paystack.
const form = document.querySelector('[data-give-form]');

if (form) {
    const amountInput = form.querySelector('[data-amount]');
    const amountLabel = form.querySelector('[data-amount-label]');
    const presets = [...form.querySelectorAll('[data-preset]')];
    const error = form.querySelector('[data-give-error]');

    const parseAmount = () => Number(amountInput.value.replace(/[^\d]/g, '')) || 0;
    const update = () => {
        const amount = parseAmount();
        amountLabel.textContent = amount.toLocaleString('en-NG');
        for (const p of presets) p.setAttribute('aria-pressed', String(Number(p.dataset.preset) === amount));
    };

    for (const p of presets) {
        p.addEventListener('click', () => {
            amountInput.value = Number(p.dataset.preset).toLocaleString('en-NG');
            update();
        });
    }
    amountInput.addEventListener('input', () => {
        const amount = parseAmount();
        amountInput.value = amount ? amount.toLocaleString('en-NG') : '';
        update();
    });
    update();

    form.addEventListener('submit', (e) => {
        let message = '';
        if (parseAmount() < 100) message = 'Please enter an amount of at least ₦100.';
        else if (!form.elements.email.checkValidity()) message = 'Please enter a valid email so we can send your receipt.';
        else if (!('paymentsOn' in form.dataset)) message = 'Online giving is not switched on yet. Please use the bank transfer details below for now.';
        if (message) e.preventDefault();
        error.hidden = !message;
        error.textContent = message;
    });

    // "Give to the building fund" preselects that fund and jumps to the form.
    document.querySelector('[data-pick-fund]')?.addEventListener('click', () => {
        const appeal = form.querySelector('[data-appeal-fund]');
        if (appeal) appeal.checked = true;
    });
}
