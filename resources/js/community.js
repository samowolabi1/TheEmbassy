// Community chat: send without reloading, pick up new messages every few seconds,
// replies, reactions, polls, and the timed Bible quiz. Every form also works without it.

const chat = document.querySelector('[data-chat]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const request = async (url, options = {}) => {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });
    let data = {};
    try {
        data = await res.json();
    } catch {
        // Not JSON, e.g. an expired session page.
    }
    if (!res.ok) {
        const error = new Error(Object.values(data.errors ?? {}).flat()[0] || data.message || 'Something went wrong. Try again.');
        error.status = res.status;
        throw error;
    }
    return data;
};

const toNode = (html) => {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
};

if (chat) {
    const list = chat.querySelector('[data-chat-list]');
    const scroller = chat.querySelector('[data-chat-scroll]');
    const composer = chat.querySelector('[data-composer]');
    let since = chat.dataset.since;
    let after = Number(chat.dataset.after || 0);

    const nearBottom = () => scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
    const toBottom = () => (scroller.scrollTop = scroller.scrollHeight);

    const showError = (text) => {
        let box = chat.querySelector('.chat__error');
        if (!box) {
            box = document.createElement('p');
            box.className = 'chat__error';
            box.setAttribute('role', 'alert');
            (composer ?? scroller).before(box);
        }
        box.textContent = text;
        clearTimeout(box.timer);
        box.timer = setTimeout(() => box.remove(), 6000);
    };

    // Reply buttons only make sense with this script running.
    const enhance = (root) => root.querySelectorAll('[data-reply]').forEach((b) => (b.hidden = !composer));

    const todayLabel = () => {
        const days = list.querySelectorAll('.chat__day');
        const last = days[days.length - 1];
        if (!last || last.textContent.trim() !== 'Today') {
            const li = document.createElement('li');
            li.className = 'chat__day';
            li.innerHTML = '<span>Today</span>';
            list.append(li);
        }
    };

    // Put a message on the page: replace it if it's there (unless someone is using its menu), or add it.
    const place = (id, html, { force = false } = {}) => {
        const existing = document.getElementById(`m${id}`);
        const node = toNode(html);
        enhance(node);
        if (existing) {
            const busy = existing.contains(document.activeElement) || existing.querySelector('details[open]');
            if (!busy || force) existing.replaceWith(node);
            return false;
        }
        chat.querySelector('[data-chat-empty]')?.remove();
        todayLabel();
        list.append(node);
        after = Math.max(after, Number(id));
        return true;
    };

    enhance(list);
    // Fill the screen with the chat, like an app (the site header is a scroll away).
    chat.closest('.community')?.scrollIntoView({ block: 'start' });
    if (!location.hash) toBottom();

    // Sending.
    if (composer) {
        const body = composer.querySelector('[data-composer-body]');
        const image = composer.querySelector('[data-composer-image]');
        const fileNote = composer.querySelector('[data-composer-file]');
        const replyBox = composer.querySelector('[data-reply-preview]');
        const replyInput = composer.querySelector('[data-reply-input]');
        const pollBox = composer.querySelector('[data-poll]');

        const grow = () => {
            body.style.height = 'auto';
            body.style.height = `${Math.min(body.scrollHeight, 160)}px`;
        };
        body.addEventListener('input', grow);

        image.addEventListener('change', () => (fileNote.textContent = image.files[0] ? `📷 ${image.files[0].name}` : ''));

        // Enter sends on computers; Shift+Enter (and Enter on phones) adds a new line.
        body.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !matchMedia('(pointer: coarse)').matches) {
                e.preventDefault();
                composer.requestSubmit();
            }
        });

        const clearReply = () => {
            replyInput.value = '';
            replyBox.hidden = true;
        };
        replyBox.querySelector('[data-reply-cancel]').addEventListener('click', clearReply);

        chat.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-reply]');
            if (!btn) return;
            const msg = btn.closest('[data-message-id]');
            replyInput.value = btn.dataset.reply;
            replyBox.querySelector('[data-reply-name]').textContent = msg.dataset.author;
            replyBox.hidden = false;
            body.focus();
        });

        composer.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = composer.querySelector('[type="submit"]');
            button.disabled = true;
            try {
                const data = await request(composer.action, { method: 'POST', body: new FormData(composer) });
                place(data.id, data.html);
                composer.reset();
                clearReply();
                fileNote.textContent = '';
                pollBox.open = false;
                grow();
                toBottom();
            } catch (err) {
                showError(err.message);
            }
            button.disabled = false;
            body.focus();
        });
    }

    // Reactions, votes, edits, removals and reports.
    chat.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-community-action]');
        if (!form) return;
        e.preventDefault();
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
        try {
            const data = await request(form.action, { method: 'POST', body: new FormData(form) });
            if (form.hasAttribute('data-report')) {
                const menu = form.closest('details');
                menu.querySelector('summary').textContent = 'Reported';
                menu.open = false;
                return;
            }
            place(data.id, data.html, { force: true });
        } catch (err) {
            showError(err.message);
        }
    });

    // Close an open menu when clicking elsewhere.
    document.addEventListener('click', (e) => {
        for (const open of chat.querySelectorAll('.msg__menu[open]')) {
            if (!open.contains(e.target)) open.open = false;
        }
    });

    // New and changed messages: every 4 seconds while the page is in view, every 30 when it isn't.
    let timer;
    const check = async () => {
        clearTimeout(timer);
        try {
            const url = new URL(chat.dataset.updatesUrl, location.origin);
            url.searchParams.set('since', since);
            url.searchParams.set('after', after);
            url.searchParams.set('visible', document.hidden ? '0' : '1');
            const data = await request(url);
            const stick = nearBottom();
            let added = false;
            for (const m of data.messages) added = place(m.id, m.html) || added;
            since = data.now;
            if (added && stick) toBottom();
            if (composer && data.can_post === false) location.reload();
        } catch (err) {
            if (err.status === 401 || err.status === 403 || err.status === 419) return; // signed out
        }
        timer = setTimeout(check, document.hidden ? 30000 : 4000);
    };
    timer = setTimeout(check, 4000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) check();
    });
}

// Timed quiz: one question at a time with a countdown; moves on when answered or out of time.
const quiz = document.querySelector('[data-quiz]');
if (quiz) {
    const questions = [...quiz.querySelectorAll('[data-quiz-q]')];
    const timerEl = quiz.querySelector('[data-quiz-timer]');
    const submit = quiz.querySelector('[data-quiz-submit]');
    const perQuestion = Number(quiz.dataset.seconds);
    const deadline = Date.now() + Number(quiz.dataset.deadline) * 1000;
    let index = 0;
    let left = perQuestion;
    let tick;
    let sent = false;

    const finish = () => {
        if (sent) return;
        sent = true;
        clearInterval(tick);
        quiz.submit();
    };

    const show = (i) => {
        if (i >= questions.length || Date.now() >= deadline) return finish();
        index = i;
        questions.forEach((q, n) => (q.hidden = n !== i));
        left = Math.min(perQuestion, Math.ceil((deadline - Date.now()) / 1000));
        timerEl.textContent = `⏱ ${left}s`;
        questions[i].querySelector('input')?.focus();
    };

    timerEl.hidden = false;
    submit.hidden = true;
    questions.forEach((q) => {
        q.addEventListener('change', () => setTimeout(() => show(index + 1), 350));
    });
    tick = setInterval(() => {
        left -= 1;
        timerEl.textContent = `⏱ ${Math.max(left, 0)}s`;
        if (left <= 0) show(index + 1);
    }, 1000);
    show(0);
}
