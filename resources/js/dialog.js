// Church-styled stand-ins for the browser's confirm() and prompt() boxes.
//
//   <form data-confirm="Delete this photo?">  asks before it submits.
//   Optional: data-confirm-ok="Delete" (button label), data-confirm-text="More detail".
//
//   import { ask } from './dialog';
//   if (await ask({ title: 'Send now?' })) ...            -> true / false
//   const url = await ask({ title: 'Link', input: {...} }) -> the text, or false

const DANGER = /\b(delete|remove|cancel|withdraw|take .* down|revoke|deactivate|reject|decline|stop|end|unlink|disconnect|clear|reset|archive|block|ban)\b/i;

let dialog;

function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'ask';
    dialog.setAttribute('aria-labelledby', 'ask-title');
    dialog.innerHTML = `
        <form method="dialog" class="ask__box">
            <span class="ask__icon" aria-hidden="true"></span>
            <h2 class="ask__title" id="ask-title"></h2>
            <p class="ask__text"></p>
            <label class="ask__field" hidden><span class="ask__label"></span><input type="text" autocomplete="off"></label>
            <div class="ask__actions">
                <button type="submit" value="cancel" class="btn btn--outline ask__cancel" formnovalidate></button>
                <button type="submit" value="ok" class="btn ask__ok"></button>
            </div>
        </form>`;
    // Tapping the dimmed backdrop closes it, like the Cancel button.
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close('cancel');
    });
    document.body.append(dialog);
}

export function ask({ title, text = '', ok = 'Yes', cancel = 'Go back', danger = null, input = null } = {}) {
    if (!dialog) build();
    if (dialog.open) dialog.close('cancel');

    const isDanger = danger ?? DANGER.test(`${title} ${ok}`);
    dialog.classList.toggle('ask--danger', isDanger);
    dialog.querySelector('.ask__icon').textContent = isDanger ? '!' : '?';
    dialog.querySelector('.ask__title').textContent = title;
    const p = dialog.querySelector('.ask__text');
    p.textContent = text;
    p.hidden = !text;
    dialog.querySelector('.ask__cancel').textContent = cancel;
    const okButton = dialog.querySelector('.ask__ok');
    okButton.textContent = ok;
    okButton.classList.toggle('ask__ok--danger', isDanger);
    okButton.classList.toggle('btn--gold', !isDanger);

    const field = dialog.querySelector('.ask__field');
    const box = field.querySelector('input');
    field.hidden = !input;
    box.required = !!input;
    if (input) {
        field.querySelector('.ask__label').textContent = input.label ?? '';
        box.type = input.type ?? 'text';
        box.placeholder = input.placeholder ?? '';
        box.value = input.value ?? '';
    }

    const opener = document.activeElement;
    dialog.returnValue = '';
    dialog.showModal();
    (input ? box : okButton).focus();

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => {
            opener?.focus?.({ preventScroll: true });
            if (dialog.returnValue !== 'ok') return resolve(false);
            resolve(input ? box.value.trim() : true);
        }, { once: true });
    });
}

// The wording for a form's confirm box: its data-confirm message, and the
// clicked button's own label on the OK button ("Give a temporary password").
export function askForForm(form, submitter) {
    const label = (form.dataset.confirmOk || submitter?.textContent || '').replace(/\s+/g, ' ').trim();
    // "Send these? This can't be undone." -> the question as the heading, the rest under it.
    const [, question = form.dataset.confirm, rest = ''] = form.dataset.confirm.match(/^(.+?\?)\s+(.+)$/s) || [];
    return ask({
        title: question,
        text: form.dataset.confirmText || rest,
        ok: label.length <= 32 && /[a-z]{2}/i.test(label) ? label : 'Yes',
    });
}

// Every <form data-confirm> on the page, including ones added later.
// Forms the community chat sends itself ask from community.js.
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.hasAttribute('data-community-action')) return;
    if (form.dataset.confirmed) {
        delete form.dataset.confirmed;
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    const submitter = e.submitter;
    if (await askForForm(form, submitter)) {
        form.dataset.confirmed = '1';
        submitter && submitter.form === form ? form.requestSubmit(submitter) : form.requestSubmit();
    }
}, true);
