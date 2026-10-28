// Article editor: Markdown toolbar, picture upload, word count, status date picker,
// and a warning before leaving with unsaved changes. The plain textarea works without it.

const editor = document.querySelector('[data-md-editor]');

if (editor) {
    const wrap = (before, after = before, placeholder = 'text') => {
        const { selectionStart: start, selectionEnd: end, value } = editor;
        const chosen = value.slice(start, end) || placeholder;
        editor.setRangeText(before + chosen + after, start, end, 'end');
        editor.setSelectionRange(start + before.length, start + before.length + chosen.length);
        editor.focus();
        editor.dispatchEvent(new Event('input'));
    };

    // Insert a block on its own lines, with blank lines around it.
    const block = (text) => {
        const { selectionStart: start, selectionEnd: end, value } = editor;
        const before = value.slice(0, start).replace(/\n*$/, '');
        const insert = (before ? '\n\n' : '') + text + '\n\n';
        editor.setRangeText(insert, before.length, end, 'end');
        editor.focus();
        editor.dispatchEvent(new Event('input'));
    };

    const prefixLines = (make) => {
        const { selectionStart: start, selectionEnd: end, value } = editor;
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lines = (value.slice(lineStart, end) || 'Item').split('\n');
        editor.setRangeText(lines.map((l, i) => make(i) + l.replace(/^(\s*([-*]|\d+\.)\s+|#+\s+)/, '')).join('\n'), lineStart, end, 'end');
        editor.focus();
        editor.dispatchEvent(new Event('input'));
    };

    const actions = {
        h2: () => prefixLines(() => '## '),
        h3: () => prefixLines(() => '### '),
        bold: () => wrap('**'),
        italic: () => wrap('*'),
        link: () => {
            const url = window.prompt('Link address (https://…)');
            if (url) wrap('[', `](${url.trim()})`, 'link text');
        },
        list: () => prefixLines(() => '- '),
        numbered: () => prefixLines((i) => `${i + 1}. `),
        scripture: () => block('> "The words of the verse."\n> — Book 1:1 (KJV)'),
        reflect: () => block(':::reflect Reflect this week\n1. A question to think about\n2. Another question\n:::'),
    };

    for (const btn of document.querySelectorAll('[data-md]')) {
        btn.addEventListener('click', () => actions[btn.dataset.md]?.());
    }

    // Pictures go up straight away and land where the cursor is.
    const picker = document.querySelector('[data-md-image]');
    const status = document.querySelector('[data-md-status]');
    picker?.addEventListener('change', async () => {
        const file = picker.files[0];
        if (!file) return;
        const note = status.firstChild.textContent;
        status.firstChild.textContent = 'Uploading picture… ';
        const body = new FormData();
        body.append('image', file);
        try {
            const res = await fetch(picker.dataset.uploadUrl, {
                method: 'POST',
                body,
                headers: { 'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value, Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Upload failed');
            block(data.markdown);
            status.firstChild.textContent = note;
        } catch (e) {
            status.firstChild.textContent = `Picture not added: ${e.message}. `;
        }
        picker.value = '';
    });

    const counter = document.querySelector('[data-word-count]');
    const count = () => {
        const words = editor.value.trim().split(/\s+/).filter(Boolean).length;
        counter.textContent = `${words} words · about ${Math.max(1, Math.ceil(words / 220))} min read.`;
    };
    editor.addEventListener('input', count);
    count();
}

// Show the date picker only for "Schedule".
const publishAt = document.querySelector('[data-publish-at]');
if (publishAt) {
    const sync = () => (publishAt.hidden = document.querySelector('[data-status]:checked')?.value !== 'scheduled');
    document.querySelectorAll('[data-status]').forEach((r) => r.addEventListener('change', sync));
    sync();
}

// Warn before leaving a form with changes that haven't been saved.
for (const form of document.querySelectorAll('form[data-unsaved-warning]')) {
    let dirty = false;
    form.addEventListener('input', () => (dirty = true));
    form.addEventListener('change', () => (dirty = true));
    form.addEventListener('submit', () => (dirty = false));
    window.addEventListener('beforeunload', (e) => {
        if (dirty) e.preventDefault();
    });
}

// Announcement sending: SMS length and pages, and keep the WhatsApp link in step with the text.
const smsText = document.querySelector('[data-sms-text]');
if (smsText) {
    const count = document.querySelector('[data-sms-count]');
    const share = document.querySelector('[data-whatsapp-share]');
    const sync = () => {
        const n = [...smsText.value].length;
        const pages = n <= 160 ? 1 : Math.ceil(n / 153);
        count.textContent = `${n} characters · ${pages} SMS ${pages === 1 ? 'page' : 'pages'} per person.`;
        if (share) share.href = `https://wa.me/?text=${encodeURIComponent(smsText.value)}`;
    };
    smsText.addEventListener('input', sync);
    sync();
}
