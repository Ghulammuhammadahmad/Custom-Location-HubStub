(function () {
    const initEditor = () => {
        const textarea = document.getElementById('clhs_ai_prompt');
        if (textarea && typeof EasyMDE !== 'undefined') {
            new EasyMDE({
                element: textarea,
                forceSync: true,
                spellChecker: false,
                status: false,
                autofocus: false,
                toolbar: [
                    'bold', 'italic', 'heading', '|',
                    'quote', 'unordered-list', 'ordered-list', '|',
                    'link', 'image', '|',
                    'preview', 'side-by-side', 'guide'
                ]
            });
        }
    };

    const initTags = () => {
        const namesInput = document.getElementById('clhs_page_name');
        const tagsContainer = document.getElementById('clhs_page_name_tags');
        if (!namesInput || !tagsContainer) return;

        const renderTags = () => {
            tagsContainer.innerHTML = '';
            const tags = namesInput.value
                .split(',')
                .map(t => t.trim())
                .filter(Boolean);
            tags.forEach(tag => {
                const span = document.createElement('span');
                span.textContent = tag;
                span.style.cssText = 'background:#e5f2ff;border:1px solid #c3d9ff;border-radius:12px;padding:4px 10px;font-size:12px;';
                tagsContainer.appendChild(span);
            });
        };

        namesInput.addEventListener('input', renderTags);
        renderTags();
    };

    const onReady = () => {
        initEditor();
        initTags();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})();

