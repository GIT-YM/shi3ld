(function () {
    'use strict';
    const script = document.currentScript;
    if (!script?.dataset.locateUrl || window.self !== window.top) return;
    if (script.dataset.locateUrl.includes('/shi3ld_content/') && !document.cookie.split(';').some(cookie => cookie.trim() === 'shi3ld_editor_available=1')) return;
    fetch(script.dataset.locateUrl, {credentials:'same-origin', cache:'no-store', redirect:'error', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
        .then(response => response.ok ? response.json() : null)
        .then(data => {
            if (!data?.links?.length) return;
            if (!document.getElementById('shi3ld-content-editor-style')) { const style = document.createElement('link'); style.id = 'shi3ld-content-editor-style'; style.rel = 'stylesheet'; style.href = '/services/assets/content-editor.css?v=20261021.3'; document.head.append(style); }
            const toolbar = document.querySelector('.shi3ld-content-toolbar') || document.createElement('nav'); toolbar.className = 'shi3ld-content-toolbar'; toolbar.setAttribute('aria-label','Website content editing');
            if (!toolbar.children.length) { const label = document.createElement('span'); label.textContent = 'SHI3LD EDITOR'; toolbar.append(label); }
            data.links.forEach(item => { const url = new URL(item.url, window.location.origin); if (url.origin !== window.location.origin || [...toolbar.querySelectorAll('a')].some(link => link.href === url.href)) return; const link = document.createElement('a'); link.href = url.href; link.textContent = item.title; toolbar.append(link); });
            document.body.append(toolbar);
        }).catch(function () {});
}());
