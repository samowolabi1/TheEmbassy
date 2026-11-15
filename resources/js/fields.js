// Form field helpers used on every page; the forms still work without them.

// Show or hide what's typed in a password box, with an eye button inside the box.
const eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
const eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.7 8.5 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>';

for (const input of document.querySelectorAll('input[type="password"]')) {
    if (input.parentElement.classList.contains('password-field')) continue;
    const wrap = document.createElement('span');
    wrap.className = 'password-field';
    input.before(wrap);
    wrap.append(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-field__toggle';
    const sync = () => {
        const shown = input.type === 'text';
        button.innerHTML = shown ? eyeOff : eye;
        button.setAttribute('aria-label', shown ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(shown));
    };
    button.addEventListener('click', (e) => {
        e.preventDefault();
        input.type = input.type === 'password' ? 'text' : 'password';
        sync();
        input.focus();
    });
    sync();
    wrap.append(button);
}

// Money boxes: <input type="number" data-money> shows the currency sign and adds commas as you
// type (₦ 84,500). The number the server gets is unchanged: it stays in the original input,
// now hidden. data-money="👑" uses another sign.
const withCommas = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

const tidy = (text, decimals) => {
    let clean = text.replace(/[^\d.]/g, '');
    const dot = clean.indexOf('.');
    if (dot !== -1) clean = clean.slice(0, dot + 1) + clean.slice(dot + 1).replace(/\./g, '');
    let [whole, fraction] = clean.split('.');
    whole = whole.replace(/^0+(?=\d)/, '');
    if (!decimals || fraction === undefined) return { raw: whole, shown: withCommas(whole) };
    fraction = fraction.slice(0, decimals);
    return { raw: `${whole || '0'}.${fraction}`, shown: `${withCommas(whole || '0')}.${fraction}` };
};

for (const input of document.querySelectorAll('input[data-money]')) {
    const symbol = input.dataset.money || '₦';
    const decimals = String(input.step || '').includes('.') ? 2 : 0;
    const min = input.min !== '' ? Number(input.min) : null;
    const max = input.max !== '' ? Number(input.max) : null;

    const shown = document.createElement('input');
    shown.type = 'text';
    shown.inputMode = decimals ? 'decimal' : 'numeric';
    shown.autocomplete = 'off';
    shown.className = `${input.className} money-field__input`.trim();
    shown.required = input.required;
    shown.disabled = input.disabled;
    shown.readOnly = input.readOnly;
    for (const attr of ['id', 'aria-label', 'aria-describedby', 'title']) {
        if (input.hasAttribute(attr)) {
            shown.setAttribute(attr, input.getAttribute(attr));
            input.removeAttribute(attr);
        }
    }
    if (input.placeholder) shown.placeholder = input.placeholder.replace(/\d{4,}/g, (n) => withCommas(n));

    const wrap = document.createElement('span');
    wrap.className = 'money-field';
    if (input.getAttribute('style')) {
        wrap.setAttribute('style', input.getAttribute('style'));
        input.removeAttribute('style');
    }
    if (input.classList.contains('select-sm')) wrap.classList.add('money-field--sm');
    const sign = document.createElement('span');
    sign.className = 'money-field__symbol';
    sign.setAttribute('aria-hidden', 'true');
    sign.textContent = symbol;
    if ([...symbol].length > 1 || /\p{Extended_Pictographic}/u.test(symbol)) wrap.classList.add('money-field--wide');

    input.before(wrap);
    wrap.append(sign, shown, input);
    input.type = 'hidden';

    const check = () => {
        const value = input.value === '' ? null : Number(input.value);
        let message = '';
        if (value !== null && min !== null && value < min) message = `Enter at least ${symbol}${withCommas(String(min))}.`;
        if (value !== null && max !== null && value > max) message = `Enter no more than ${symbol}${withCommas(String(max))}.`;
        shown.setCustomValidity(message);
    };
    const update = () => {
        // Keep the cursor after the same number of digits as the commas move.
        const before = shown.value.slice(0, shown.selectionStart ?? shown.value.length).replace(/[^\d.]/g, '').length;
        const { raw, shown: text } = tidy(shown.value, decimals);
        shown.value = text;
        input.value = raw;
        let seen = 0;
        let caret = text.length;
        for (let i = 0; i < text.length; i++) {
            if (seen === before) { caret = i; break; }
            if (/[\d.]/.test(text[i])) seen++;
        }
        if (document.activeElement === shown) shown.setSelectionRange(caret, caret);
        check();
    };

    shown.value = tidy(input.value, decimals).shown;
    if (decimals && /\.0+$/.test(shown.value)) shown.value = shown.value.replace(/\.0+$/, '');
    check();
    shown.addEventListener('input', update);
}
