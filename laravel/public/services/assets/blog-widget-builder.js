(function () {
    'use strict';
    const element = document.getElementById('blog-widget-config');
    if (!element || !window.Shi3ldBlogControls) return;
    const config = JSON.parse(element.textContent), controls = window.Shi3ldBlogControls;
    const settings = document.getElementById('blog-widget-settings'), code = document.getElementById('blog-widget-code');
    const status = document.getElementById('blog-widget-status'), frame = document.getElementById('blog-widget-frame');
    settings.innerHTML = controls.render(config.fields);
    let timer, busy = false, pending = false;
    function updateCode() { code.value = controls.directive(config, controls.read(settings, config.fields)); }
    async function preview() {
        if (busy) { pending = true; return; }
        busy = true; status.textContent = 'Updating preview…'; updateCode();
        const body = new FormData(); body.set('form_key',config.csrf); body.set('settings',JSON.stringify(controls.read(settings, config.fields)));
        try {
            const response = await fetch(config.url, {method:'POST',credentials:'same-origin',redirect:'error',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || 'The preview could not be loaded.');
            code.value = data.directive;
            frame.srcdoc = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/services/assets/site.css"><link rel="stylesheet" href="/services/assets/blog.css?v=20261021.3"></head><body style="padding:24px">' + (data.html || '<p>No published articles match these settings. The widget will stay hidden until matching articles are published.</p>') + '</body></html>';
            status.textContent = 'Preview updated';
        } catch (error) { status.textContent = error.message || 'Sign in again and refresh the builder.'; }
        finally { busy = false; if (pending) { pending = false; preview(); } }
    }
    settings.addEventListener('input', () => { updateCode(); window.clearTimeout(timer); timer = window.setTimeout(preview, 600); });
    settings.addEventListener('change', () => { updateCode(); window.clearTimeout(timer); timer = window.setTimeout(preview, 100); });
    document.getElementById('blog-widget-preview').addEventListener('click', preview);
    document.getElementById('blog-widget-copy').addEventListener('click', async () => { updateCode(); try { await navigator.clipboard.writeText(code.value); status.textContent = 'Shortcode copied'; } catch (_) { code.select(); status.textContent = 'Select and copy the shortcode above.'; } });
    document.querySelectorAll('[data-widget-device]').forEach(button => button.addEventListener('click', () => { frame.style.width = button.dataset.widgetDevice; }));
    frame.addEventListener('load', () => { frame.contentDocument?.addEventListener('click', event => event.preventDefault()); frame.contentDocument?.addEventListener('submit', event => event.preventDefault()); });
    updateCode(); preview();
}());
