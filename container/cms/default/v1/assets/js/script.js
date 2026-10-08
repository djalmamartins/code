/**
 * Moves Code | CMS Interactions
 *
 * Adiciona interações acessíveis e confirmação a ações do CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1
 */

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm || 'Confirmar esta ação?')) event.preventDefault();
    });
});

const sidebar = document.querySelector('#cms-sidebar');
const shell = document.querySelector('.cms-shell');
const toggles = document.querySelectorAll('.sidebar-toggle');

if (sidebar && shell && toggles.length) {
    const mobile = window.matchMedia('(max-width: 700px)');
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Fechar menu');
    sidebar.after(backdrop);

    const setOpen = (open) => {
        if (mobile.matches) {
            sidebar.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-visible', open);
        } else {
            shell.classList.toggle('is-collapsed', open);
        }
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(mobile.matches ? open : !open));
            toggle.setAttribute('aria-label', mobile.matches ? (open ? 'Fechar menu' : 'Abrir menu') : (open ? 'Expandir menu' : 'Recolher menu'));
        });
    };

    toggles.forEach((toggle) => toggle.addEventListener('click', () => {
        const open = mobile.matches ? !sidebar.classList.contains('is-open') : !shell.classList.contains('is-collapsed');
        setOpen(open);
    }));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a.nav-link').forEach((link) => link.addEventListener('click', () => {
        if (mobile.matches) setOpen(false);
    }));
    mobile.addEventListener('change', () => setOpen(false));
}

const editorDemo = document.querySelector('[data-moves-editor-demo]');
if (editorDemo && typeof window.MovesEditor === 'function') {
    const output = editorDemo.querySelector('#moves-editor-output');
    const editor = new window.MovesEditor('#moves-editor', {
        placeholder: 'Comece a escrever…',
        minHeight: 360,
        initialHTML: '<h2>Bem-vindo ao Moves Editor</h2><p>Use a barra de ferramentas para formatar este conteúdo de demonstração.</p>',
        onChange(html) {
            if (output) output.value = html;
        },
        imageUploader() {
            return Promise.reject(new Error('Uploads estão desativados nesta demonstração.'));
        },
    });

    editorDemo.querySelectorAll('[data-mode="html"], [data-action="link"], [data-action="image"]').forEach((control) => control.remove());
    editor.editor.addEventListener('paste', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const text = event.clipboardData?.getData('text/plain') ?? '';
        document.execCommand('insertText', false, text);
    }, true);
    editor.editor.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
    editor.editor.addEventListener('dragover', (event) => event.preventDefault(), true);
    editor.editor.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    if (output) output.value = editor.getHTML();
}

const pageEditorForm = document.querySelector('[data-moves-page-editor]');
if (pageEditorForm && typeof window.MovesEditor === 'function') {
    const field = pageEditorForm.querySelector('#page-content');
    const editor = new window.MovesEditor('#moves-page-editor', {
        placeholder: 'Escreva o conteúdo da página…',
        minHeight: 420,
        initialHTML: field?.value ?? '',
        onChange(html) {
            if (field) field.value = html;
        },
        imageUploader() {
            return Promise.reject(new Error('Uploads de imagem estão desativados.'));
        },
    });

    pageEditorForm.querySelectorAll('[data-mode="html"], [data-action="image"]').forEach((control) => control.remove());
    editor.editor.addEventListener('paste', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const text = event.clipboardData?.getData('text/plain') ?? '';
        document.execCommand('insertText', false, text);
    }, true);
    editor.editor.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
    editor.editor.addEventListener('dragover', (event) => event.preventDefault(), true);
    pageEditorForm.addEventListener('submit', () => {
        if (field) field.value = editor.getHTML();
    });
    if (field) field.hidden = true;

    const titleField = pageEditorForm.querySelector('[name="title"]');
    const slugField = pageEditorForm.querySelector('[name="slug"]');
    const slugPreview = pageEditorForm.querySelector('[data-page-slug-preview]');
    let slugChanged = Boolean(slugField?.value);
    slugField?.addEventListener('input', () => { slugChanged = true; });
    titleField?.addEventListener('input', () => {
        if (!slugField || slugChanged) return;
        slugField.value = titleField.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        if (slugPreview) slugPreview.textContent = slugField.value || 'slug-da-pagina';
    });
    slugField?.addEventListener('input', () => {
        if (slugPreview) slugPreview.textContent = slugField.value || 'slug-da-pagina';
    });
}

const articleEditorForm = document.querySelector('[data-moves-article-editor]');
if (articleEditorForm && typeof window.MovesEditor === 'function') {
    const field = articleEditorForm.querySelector('#article-content');
    const editor = new window.MovesEditor('#moves-article-editor', {
        placeholder: 'Escreva o conteúdo do artigo…',
        minHeight: 420,
        initialHTML: field?.value ?? '',
        onChange(html) {
            if (field) field.value = html;
        },
        imageUploader() {
            return Promise.reject(new Error('Uploads de imagem estão desativados.'));
        },
    });

    articleEditorForm.querySelectorAll('[data-mode="html"], [data-action="image"]').forEach((control) => control.remove());
    editor.editor.addEventListener('paste', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
        const text = event.clipboardData?.getData('text/plain') ?? '';
        document.execCommand('insertText', false, text);
    }, true);
    editor.editor.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
    editor.editor.addEventListener('dragover', (event) => event.preventDefault(), true);
    articleEditorForm.addEventListener('submit', () => {
        if (field) field.value = editor.getHTML();
    });
    if (field) field.hidden = true;

    const titleField = articleEditorForm.querySelector('[name="title"]');
    const slugField = articleEditorForm.querySelector('[name="slug"]');
    const slugPreview = articleEditorForm.querySelector('[data-article-slug-preview]');
    let slugChanged = Boolean(slugField?.value);
    slugField?.addEventListener('input', () => { slugChanged = true; });
    titleField?.addEventListener('input', () => {
        if (!slugField || slugChanged) return;
        slugField.value = titleField.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        if (slugPreview) slugPreview.textContent = slugField.value || 'slug-do-artigo';
    });
    slugField?.addEventListener('input', () => {
        if (slugPreview) slugPreview.textContent = slugField.value || 'slug-do-artigo';
    });
}
