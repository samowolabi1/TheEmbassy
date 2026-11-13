// The uploaded birthday design editor: the preview follows the sliders.

const editor = document.querySelector('[data-design-editor]');

if (editor) {
    const preview = editor.querySelector('[data-design-preview]');
    let timer;

    const refresh = () => {
        const params = new URLSearchParams();
        for (const field of editor.querySelectorAll('[name^="layout["]')) {
            const value = field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
            params.set(field.name, value);
        }
        // Every setting is sent, so the preview matches the form exactly.
        params.set('layout[_]', '1');
        preview.style.opacity = '0.6';
        const url = new URL(editor.dataset.preview, location.href);
        for (const [k, v] of params) url.searchParams.set(k, v);
        const next = new Image();
        next.onload = () => {
            preview.src = next.src;
            preview.style.opacity = '1';
        };
        next.src = url.toString();
    };

    editor.addEventListener('input', (e) => {
        const out = editor.querySelector(`output[data-for="${e.target.name?.match(/\[(\w+)\]/)?.[1]}"]`);
        if (out) out.textContent = e.target.name.includes('name_size') ? e.target.value : `${Math.round(e.target.value)}%`;
        clearTimeout(timer);
        timer = setTimeout(refresh, 250);
    });
    editor.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 100);
    });
}
