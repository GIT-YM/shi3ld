(function () {
    'use strict';
    const configElement = document.getElementById('cms-editor-config');
    if (!configElement) return;
    const config = JSON.parse(configElement.textContent);
    const blogControls = window.Shi3ldBlogControls;
    const host = document.getElementById('cms-editor');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let state = config.state, payload = {...state.payload}, source, frameDocument, frameRoot, selected = null;
    let dirty = false, busy = false, saving = false, readOnly = false, nodeCounter = 0, rendering = 0, canvasReady = false;
    const parser = new DOMParser();
    const recoveryKey = 'shi3ld-editor:' + config.framework + ':' + config.actor + ':' + config.key;
    let recoveryTimer, mediaTarget = 'node', mediaCallback = null, advanced = null, blogEditorial = null;
    const keepRecovery = () => { try { if (source && dirty) sessionStorage.setItem(recoveryKey, JSON.stringify({payload:currentPayload(), version:state.version})); } catch (_) {} };
    host.innerHTML = `<div class="editor-app">
        <header class="editor-top"><a class="editor-back" href="${escape(config.backUrl)}">← Content</a><span class="editor-brand">SHI3LD <small>VISUAL EDITOR</small></span>
        <select id="editor-navigation" aria-label="Choose page">${config.navigation.map(item => `<option value="${escape(item.url)}" ${(config.key ? item.key === config.key : item.title === config.title) ? 'selected' : ''}>${escape(item.title)}</option>`).join('')}</select>
        <span id="editor-status" role="status">${state.has_draft ? 'Saved draft' : config.article && !config.articlePublished ? 'Unpublished article' : 'Published content'}</span><div class="editor-actions"><button id="editor-save">Save draft</button><button id="editor-preview">Preview draft ↗</button><button id="editor-publish" class="primary" ${config.canPublish ? '' : 'disabled title="Your role cannot publish"'}>Publish</button></div></header>
        <div id="editor-notice" class="editor-notice" role="alert" hidden></div>
        <div class="editor-workspace"><aside class="editor-sidebar"><div class="editor-tabs"><button data-tab="sections" class="active">Sections</button><button data-tab="settings">Page</button><button data-tab="history">History</button></div>
        <section id="editor-sections" class="editor-tab"><div class="panel-heading"><h2>Page sections</h2><button id="editor-add" class="compact">＋ Add</button></div><p class="editor-hint">Drag to reorder. Click text, an image or a button on the page to edit it.</p><ol id="editor-section-list"></ol><div id="editor-inspector"></div></section>
        <section id="editor-settings" class="editor-tab" hidden><h2>${config.article ? 'Article settings' : 'Page settings'}</h2>${[['title',config.article ? 'Article title' : 'Page title',255],['meta_title','SEO title',255],['meta_description','SEO description',1000],...(!config.article ? [['content_heading','Content heading',255]] : [])].map(([key,label,max]) => `<label class="editor-field">${label}${key === 'meta_description' ? `<textarea data-meta="${key}" maxlength="${max}" rows="5"></textarea>` : `<input data-meta="${key}" maxlength="${max}">`}</label>`).join('')}${config.article ? articleFields() : '<p class="editor-hint">Page URLs stay unchanged. Draft settings become public when you publish.</p>'}${config.nativeUrl ? `<a class="editor-native" href="${escape(config.nativeUrl)}" target="_blank" rel="noopener">Open native CMS editor ↗</a>` : ''}</section>
        <section id="editor-history" class="editor-tab" hidden><h2>Revision history</h2><p class="editor-hint">Restoring creates a draft. Publish it when you are ready.</p><div id="editor-revisions"></div></section></aside>
        <main class="editor-canvas"><div class="editor-device-bar"><div><button data-device="desktop" class="active">Desktop</button><button data-device="tablet">Tablet</button><button data-device="mobile">Mobile</button></div><span id="editor-view-label">Editing draft</span><button id="editor-view">View only</button></div><div class="editor-frame-area"><iframe id="editor-frame" title="Website visual preview" sandbox="allow-same-origin"></iframe><div id="editor-canvas-loading">Loading your website…</div></div><div class="editor-canvas-note">${escape(config.framework)} · Changes remain private until published · Ctrl / ⌘ S saves a draft</div></main></div>
        <dialog id="editor-add-dialog"><div class="dialog-heading"><h2>Add a section</h2><button data-close-dialog="editor-add-dialog" aria-label="Close">×</button></div><p>Start with an approved layout, then make it your own.</p><div class="editor-template-grid">${[['text','Text and heading','Aa'],['image','Image and text','▧'],['columns','Two columns','▥'],['cta','Call to action','↗'],['faq','Questions and answers','?'],...(config.blogWidget && blogControls ? [['blog','Blog articles','▤']] : [])].map(([key,label,icon]) => `<button data-template="${key}"><strong>${icon}</strong><span>${label}</span></button>`).join('')}</div></dialog>
        <dialog id="editor-media-dialog"><div class="dialog-heading"><h2>Website images</h2><button data-close-dialog="editor-media-dialog" aria-label="Close">×</button></div><label class="editor-upload">Upload image <input id="editor-upload" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></label><p class="editor-hint">JPEG, PNG, GIF or WebP, up to 5 MB. Add a description after choosing an image.</p><div id="editor-media-grid"></div></dialog>
    </div>`;
    const $ = selector => host.querySelector(selector);
    function articleFields() {
        return `<h3>Article URL</h3><label class="editor-field">URL key<input data-article="slug" maxlength="180" placeholder="Generated from the title when empty"></label><p class="editor-hint">/blog/your-url-key · Use lowercase letters, numbers and hyphens. Title changes keep the current URL. Changing a published URL creates a 301 redirect when published.</p><label class="editor-field">Categories<select data-article="category_ids" multiple size="6">${config.categories.map(category => `<option value="${category.category_id}">${escape(category.title)}${Number(category.is_active) ? '' : ' (hidden)'}</option>`).join('')}</select></label><p class="editor-hint">Select one or several blog categories. Categories are managed in Content → Blog Categories.</p><label class="editor-field">Short introduction<textarea data-article="excerpt" maxlength="3000" rows="5"></textarea></label><p class="editor-hint">Leave empty to build card excerpts automatically from the article text.</p><label class="editor-field">Author<input data-article="author" maxlength="150"></label><label class="editor-field editor-checkbox"><input data-article="is_featured" type="checkbox"> Featured article</label><h3>Featured image</h3><img id="article-image-preview" class="editor-featured-preview" alt="Featured image preview" hidden><button id="article-choose-image">Choose or upload featured image</button><button id="article-remove-image">Remove featured image</button><label class="editor-field">Image description<input data-article="featured_alt" maxlength="300"></label><p class="editor-hint">A branded placeholder appears if no image is chosen. Article settings, categories and images stay private until publication.</p>`;
    }
    const notice = (message, error = false) => { const element = $('#editor-notice'); element.textContent = message; element.hidden = !message; element.classList.toggle('error', error); };
    const status = message => { $('#editor-status').textContent = message; };
    const changed = () => { rendering++; advanced?.changed(); blogEditorial?.changed(); dirty = true; status('Unsaved changes'); window.clearTimeout(recoveryTimer); recoveryTimer = window.setTimeout(keepRecovery, 500); };
    const setBusy = value => { busy = value; ['save','preview','publish'].forEach(key => { $('#editor-' + key).disabled = value || (key === 'publish' && !config.canPublish); }); };
    async function command(intent, extra = {}) {
        const body = new FormData();
        body.set(config.csrfName, config.csrf); body.set('intent', intent); body.set('version', state.version); body.set('live_hash', state.live_hash);
        Object.entries(extra).forEach(([key, value]) => body.set(key, value));
        let response;
        try { response = await fetch(config.commandUrl, {method:'POST', credentials:'same-origin', body, redirect:'error', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}}); }
        catch (_) { keepRecovery(); throw new Error('The editor could not connect. Keep this tab open, check your connection and sign in again if your session expired.'); }
        let data;
        try { data = await response.json(); } catch (_) { keepRecovery(); throw new Error('Your editor session may have expired. Keep this tab open and sign in again before reloading the editor.'); }
        if (!response.ok || data.error) throw new Error(data.error || data.message || 'This action could not be completed.');
        return data;
    }
    function loadSource(nextPayload) {
        payload = {...nextPayload}; source = document.createElement('div'); source.innerHTML = config.blogWidget && blogControls ? blogControls.canonicalize(config.blogWidget, payload.html) : payload.html;
        if (config.blogWidget && blogControls) {
            const walker = document.createTreeWalker(source, NodeFilter.SHOW_TEXT), nodes = [];
            while (walker.nextNode()) if (blogControls.parse(config.blogWidget, walker.currentNode.textContent) && !walker.currentNode.parentElement.closest('[data-editor-widget="blog-articles"]')) nodes.push(walker.currentNode);
            nodes.forEach(node => { const match = node.textContent.match(/\{\{widget\s+.+?\}\}/s); if (!match) return; const wrapper = document.createElement('section'); wrapper.setAttribute('data-editor-widget','blog-articles'); wrapper.textContent = match[0]; node.replaceWith(document.createTextNode(node.textContent.slice(0,match.index)),wrapper,document.createTextNode(node.textContent.slice(match.index + match[0].length))); });
        }
        assignNodes(); selected = null;
        host.querySelectorAll('[data-meta]').forEach(input => { input.value = payload[input.dataset.meta] || ''; });
        host.querySelectorAll('[data-article]').forEach(input => { const value = payload[input.dataset.article]; if (input.multiple) [...input.options].forEach(option => { option.selected = (value || []).map(String).includes(option.value); }); else if (input.type === 'checkbox') input.checked = Boolean(Number(value)); else input.value = value || ''; });
        featuredPreview();
        drawSections(); inspector(); advanced?.loaded(); blogEditorial?.loaded();
    }
    function assignNodes() {
        source.querySelectorAll('*').forEach(element => {
            if (element.tagName !== 'STYLE' && !element.hasAttribute('data-cms-node')) element.setAttribute('data-cms-node', 'cms-' + (++nodeCounter));
        });
    }
    function serialize(annotated = false) {
        const clone = source.cloneNode(true);
        if (!annotated) clone.querySelectorAll('[data-cms-node]').forEach(element => element.removeAttribute('data-cms-node'));
        return clone.innerHTML;
    }
    function currentPayload(annotated = false) { return {...payload, html:serialize(annotated)}; }
    const canonicalNode = key => source.querySelector('[data-cms-node="' + key + '"]');
    const sections = () => [...source.children].filter(element => element.tagName !== 'STYLE');
    function sectionFor(element) { while (element && element.parentElement !== source) element = element.parentElement; return element; }
    function sectionLabel(element) {
        const widget = element.matches('[data-editor-widget="blog-articles"]') ? element : element.querySelector('[data-editor-widget="blog-articles"]');
        if (widget) return blogControls?.parse(config.blogWidget, widget.textContent)?.heading || 'Blog articles';
        const heading = element.querySelector('h1,h2,h3');
        return (heading?.textContent.trim() || element.getAttribute('data-content-type') || element.tagName.toLowerCase()).slice(0, 65);
    }
    function drawSections() {
        $('#editor-section-list').innerHTML = sections().map((element, index) => `<li draggable="true" data-section="${escape(element.dataset.cmsNode)}" class="${selected && sectionFor(canonicalNode(selected)) === element ? 'active' : ''}"><button class="section-pick" data-pick="${escape(element.dataset.cmsNode)}"><span class="section-handle" aria-hidden="true">⠿</span><span><small>SECTION ${index + 1}</small>${escape(sectionLabel(element))}</span></button><div class="section-actions"><button data-move="up" data-key="${escape(element.dataset.cmsNode)}" title="Move up">↑</button><button data-move="down" data-key="${escape(element.dataset.cmsNode)}" title="Move down">↓</button><button data-duplicate="${escape(element.dataset.cmsNode)}" title="Duplicate section">⧉</button><button data-delete="${escape(element.dataset.cmsNode)}" title="Remove section">×</button></div></li>`).join(''); advanced?.structure();
    }
    const plainText = element => { const clone = element.cloneNode(true); clone.querySelectorAll('br').forEach(br => br.replaceWith('\n')); return clone.textContent; };
    function editableText(element) { return /^(H[1-6]|P|LI|FIGCAPTION|SUMMARY|BLOCKQUOTE|STRONG|EM|SPAN|BUTTON|TD|TH|DT|DD|CODE|PRE)$/.test(element.tagName) && [...element.querySelectorAll('*')].every(child => ['BR','STRONG','EM','B','I','U','S','A','SPAN','SUP','SUB','CODE'].includes(child.tagName)); }
    function isDynamic(element) { return element.closest('[data-editor-widget], [data-builder-ref], [data-builder-remote-widget], [data-builder-slot], [data-builder-field], [data-builder-widget="video"], [data-builder-widget="form"], [data-content-type="products"], [data-content-type="block"], [data-content-type="dynamic_block"]'); }
    function selectNode(key) {
        if (readOnly) return;
        selected = key;
        frameDocument?.querySelectorAll('[data-editor-selected]').forEach(element => element.removeAttribute('data-editor-selected'));
        const element = frameDocument?.querySelector('[data-cms-node="' + key + '"]');
        element?.setAttribute('data-editor-selected', 'true');
        drawSections(); inspector(); advanced?.selection();
    }
    function inspector() {
        const element = selected && canonicalNode(selected);
        const textElement = element?.tagName === 'A' ? (element.querySelector('[data-element="link_text"]') || element) : element;
        const panel = $('#editor-inspector');
        panel.onchange = null;
        if (!element || readOnly) { panel.innerHTML = '<p class="editor-hint inspector-empty">Choose something on the page to edit its content and appearance.</p>'; return; }
        if (element.matches('[data-editor-widget="blog-articles"]') && config.blogWidget && blogControls) {
            const values = blogControls.parse(config.blogWidget, element.textContent) || {};
            panel.innerHTML = '<h2>Blog articles</h2><p class="editor-hint">Published articles update automatically. Selecting several categories matches any of them. Empty widgets are hidden on the public website. List layout uses one column.</p>' + blogControls.render(config.blogWidget.fields, values);
            panel.onchange = () => { element.textContent = blogControls.directive(config.blogWidget, blogControls.read(panel, config.blogWidget.fields)); changed(); drawSections(); renderFrame(); };
            advanced?.inspector(panel, element, readOnly); return;
        }
        let fields = `<h2>${escape(element.tagName === 'IMG' ? 'Image' : (element.getAttribute('data-content-type') || element.tagName.toLowerCase()))}</h2>`;
        if (isDynamic(element)) {
            fields += '<p class="editor-hint">This section displays live catalogue or service content. Manage its records in the catalogue or services area. You can move, duplicate or remove the surrounding section.</p>';
        } else {
            if ((editableText(element) || (element.tagName === 'A' && !element.querySelector('svg,img,[data-content-type]'))) && !textElement.querySelector('span[class],span[data-element],a')) {
                fields += `<label class="editor-field">Text<textarea id="selected-text" rows="5">${escape(plainText(textElement))}</textarea></label><div class="editor-format"><button data-format="strong">Bold selection</button><button data-format="em">Italic selection</button></div>`;
            }
            else if (editableText(element)) fields += '<p class="editor-hint">Edit this text directly on the page to keep its links and formatting.</p>';
            if (element.tagName === 'IMG') fields += `<button id="editor-choose-image">Choose or upload image</button><label class="editor-field">Image description<input id="selected-alt" value="${escape(element.getAttribute('alt'))}" maxlength="300"></label><p class="editor-hint">Replacing an image updates the matching desktop/mobile pair.</p>`;
            if (element.tagName.toLowerCase() === 'svg') fields += '<button id="editor-choose-image">Replace brand mark with an image</button>';
            if (element.tagName === 'A') {
                const destination = (element.getAttribute('href') || '').replace(/\{\{store\s+url\s*=\s*['"]?([^'"}]*?)['"]?\s*\}\}/, (_, path) => '/' + path.replace(/^\//,''));
                fields += `<label class="editor-field">Link destination<input id="selected-href" value="${escape(destination)}" placeholder="/services/quote"></label>`;
                if (element.closest('nav')) fields += '<div class="editor-format"><button data-link-move="up">Move link left</button><button data-link-move="down">Move link right</button></div>';
            }
            if (/^(H[1-6]|P)$/.test(element.tagName)) fields += `<label class="editor-field">Text style<select id="selected-tag">${['p','h1','h2','h3','h4'].map(tag => `<option value="${tag}" ${element.tagName.toLowerCase() === tag ? 'selected' : ''}>${tag === 'p' ? 'Paragraph' : 'Heading ' + tag.slice(1)}</option>`).join('')}</select></label>`;
        }
        fields += `<h3>Appearance</h3><label class="editor-field">Alignment<select id="selected-align">${['','left','center','right'].map(value => `<option value="${value}" ${element.style.textAlign === value ? 'selected' : ''}>${value || 'Theme default'}</option>`).join('')}</select></label><div class="editor-field-row"><label class="editor-field">Text colour<input id="selected-color" type="color" value="${toHex(element.style.color, '#173d32')}"></label><label class="editor-field">Background<input id="selected-background" type="color" value="${toHex(element.style.backgroundColor, '#ffffff')}"></label></div><label class="editor-field">Padding (pixels)<input id="selected-padding" type="number" min="0" max="160" value="${parseInt(element.style.padding) || 0}"></label>`;
        if (element.getAttribute('data-content-type') === 'column') fields += `<label class="editor-field">Column width (%)<input id="selected-width" type="number" min="10" max="100" value="${parseFloat(element.style.width) || 50}"></label>`;
        panel.innerHTML = fields;
        const bind = (selector, event, callback) => panel.querySelector(selector)?.addEventListener(event, callback);
        bind('#selected-text', 'input', event => { textElement.replaceChildren(...event.target.value.split('\n').flatMap((line,index) => index ? [document.createElement('br'), document.createTextNode(line)] : [document.createTextNode(line)])); assignNodes(); changed(); drawSections(); const live = frameRoot.querySelector('[data-cms-node="' + textElement.dataset.cmsNode + '"]'); if (live) live.innerHTML = textElement.innerHTML; });
        bind('#selected-alt', 'input', event => { const figure = element.closest('[data-content-type="image"]'); (figure ? figure.querySelectorAll('img') : [element]).forEach(image => { image.setAttribute('alt', event.target.value); frameRoot.querySelector('[data-cms-node="' + image.dataset.cmsNode + '"]')?.setAttribute('alt', event.target.value); }); changed(); });
        bind('#selected-href', 'change', event => { element.setAttribute('href', event.target.value.trim()); frameRoot.querySelector('[data-cms-node="' + selected + '"]')?.setAttribute('href', event.target.value.trim()); changed(); });
        bind('#selected-tag', 'change', event => { const replacement = document.createElement(event.target.value); [...element.attributes].forEach(attr => replacement.setAttribute(attr.name, attr.value)); replacement.innerHTML = element.innerHTML; element.replaceWith(replacement); changed(); renderFrame(); inspector(); });
        [['#selected-align','textAlign'],['#selected-color','color'],['#selected-background','backgroundColor'],['#selected-padding','padding']].forEach(([selector, property]) => bind(selector, 'change', event => { element.style[property] = property === 'padding' ? Math.min(160, Math.max(0, Number(event.target.value))) + 'px' : event.target.value; const live = frameRoot.querySelector('[data-cms-node="' + selected + '"]'); if (live) live.style.cssText = element.style.cssText; changed(); }));
        bind('#editor-choose-image', 'click', openMedia);
        bind('#selected-width', 'change', event => { element.style.width = Math.min(100,Math.max(10,Number(event.target.value))) + '%'; const live = frameRoot.querySelector('[data-cms-node="' + selected + '"]'); if (live) live.style.width = element.style.width; changed(); });
        panel.querySelectorAll('[data-link-move]').forEach(button => button.addEventListener('click', () => { const sibling = button.dataset.linkMove === 'up' ? element.previousElementSibling : element.nextElementSibling; if (sibling) { if (button.dataset.linkMove === 'up') sibling.before(element); else sibling.after(element); changed(); renderFrame(); } }));
        panel.querySelectorAll('[data-format]').forEach(button => {
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => {
                const selection = frameDocument.defaultView.getSelection();
                const live = frameDocument.querySelector('[data-cms-node="' + selected + '"]');
                if (!selection.rangeCount || !live?.contains(selection.anchorNode) || !live.contains(selection.focusNode) || selection.isCollapsed) { notice('Select text within one paragraph on the page, then choose Bold or Italic.'); return; }
                const range = selection.getRangeAt(0), wrapper = frameDocument.createElement(button.dataset.format);
                wrapper.append(range.extractContents()); range.insertNode(wrapper); captureText(live); changed();
            });
        });
        advanced?.inspector(panel, element, readOnly);
    }
    function toHex(value, fallback) {
        if (/^#[0-9a-f]{6}$/i.test(value)) return value;
        const values = value?.match(/\d+/g); return values?.length >= 3 ? '#' + values.slice(0,3).map(item => Number(item).toString(16).padStart(2,'0')).join('') : fallback;
    }
    function captureText(live) {
        const original = canonicalNode(live.dataset.cmsNode);
        if (!original || isDynamic(original)) return;
        const clone = live.cloneNode(true);
        clone.querySelectorAll('*').forEach(child => { [...child.attributes].forEach(attribute => { if (!['class','style','title','href','target','rel','data-element','data-pb-style','data-cms-node','aria-label','aria-hidden'].includes(attribute.name)) child.removeAttribute(attribute.name); }); if (!['BR','STRONG','EM','B','I','U','S','A','SPAN','SUP','SUB','CODE'].includes(child.tagName)) child.replaceWith(...child.childNodes); });
        original.innerHTML = clone.innerHTML; assignNodes(); drawSections();
        const field = $('#selected-text'); if (field && selected === original.dataset.cmsNode) field.value = plainText(original);
    }
    async function initializeCanvas() {
        const response = await fetch(config.liveUrl, {credentials:'same-origin', cache:'no-store'});
        if (!response.ok) throw new Error('The website could not be opened. Sign in to Magento Admin and open the editor again if the site is offline.');
        const documentCopy = parser.parseFromString(await response.text(), 'text/html');
        documentCopy.documentElement.classList.add('js', 'shi3ld-editor-canvas');
        documentCopy.querySelectorAll('script,iframe,object,embed,meta[http-equiv]').forEach(element => element.remove());
        documentCopy.querySelectorAll('*').forEach(element => [...element.attributes].forEach(attribute => { if (/^on/i.test(attribute.name)) element.removeAttribute(attribute.name); }));
        const base = documentCopy.createElement('base'); base.href = config.liveUrl; documentCopy.head.prepend(base);
        const frame = $('#editor-frame');
        await new Promise(resolve => { frame.addEventListener('load', resolve, {once:true}); frame.srcdoc = '<!doctype html>' + documentCopy.documentElement.outerHTML; });
        frameDocument = frame.contentDocument; frameRoot = frameDocument.querySelector(config.selector);
        if (!frameRoot) throw new Error('The editable region could not be found on this page. Reload after the website cache is refreshed.');
        const style = frameDocument.createElement('style');
        style.textContent = '[data-cms-node]{cursor:pointer!important}[data-cms-node]:hover{outline:1px dashed #669780;outline-offset:3px}[data-editor-selected]{outline:2px solid #19785a!important;outline-offset:4px!important}[contenteditable=true]{cursor:text!important;min-width:1em}.shi3ld-content-toolbar{display:none!important}.shi3ld-editor-canvas [data-editor-widget="blog-articles"]{display:block!important}.shi3ld-editor-canvas .shi3ld-pb-row:has([data-editor-widget="blog-articles"]){display:block!important}[data-editor-widget="blog-articles"]:not(:has(.blog-widget)){min-height:100px;border:2px dashed #7c9c83;margin-block:20px;padding:24px}[data-editor-widget="blog-articles"]:not(:has(.blog-widget))::after{content:"Blog articles · No published articles match yet. Click to configure.";display:block;color:#315e4b;font:16px system-ui}';
        frameDocument.head.append(style);
        frameDocument.addEventListener('click', event => {
            const summary = event.target.closest('summary');
            if (summary?.parentElement.tagName === 'DETAILS') summary.parentElement.open = readOnly ? !summary.parentElement.open : true;
            const menu = event.target.closest('.menu-toggle');
            if (menu && readOnly) { const header = menu.closest('.site-header'); const open = header.dataset.open !== 'true'; header.dataset.open = String(open); menu.setAttribute('aria-expanded',String(open)); }
            if (readOnly) { event.preventDefault(); return; }
            event.preventDefault(); event.stopPropagation();
            const live = event.target.closest('[data-cms-node]');
            if (!live || !frameRoot.contains(live)) return;
            let original = canonicalNode(live.dataset.cmsNode); if (!original) return;
            original = original.closest('svg') || original;
            const dynamic = isDynamic(original);
            if (event.shiftKey && advanced) { advanced.toggle(dynamic || original); return; }
            selectNode((dynamic || original).dataset.cmsNode);
            if (!dynamic && editableText(original)) { live.setAttribute('contenteditable','true'); live.focus(); }
        }, true);
        frameDocument.addEventListener('input', event => { const live = event.target.closest('[contenteditable=true][data-cms-node]'); if (live) { captureText(live); changed(); } });
        frameDocument.addEventListener('paste', event => {
            const live = event.target.closest('[contenteditable=true]'); if (!live) return;
            event.preventDefault(); const selection = frameDocument.defaultView.getSelection(); if (!selection.rangeCount) return;
            const range = selection.getRangeAt(0); range.deleteContents(); const text = frameDocument.createTextNode(event.clipboardData.getData('text/plain')); range.insertNode(text); range.setStartAfter(text); range.collapse(true); selection.removeAllRanges(); selection.addRange(range); captureText(live); changed();
        });
        frameDocument.addEventListener('keydown', keyboardSave);
        frameDocument.addEventListener('keydown', event => {
            const live = event.target.closest('[contenteditable=true][data-cms-node]');
            if (event.key !== 'Enter' || !live || event.ctrlKey || event.metaKey) return;
            event.preventDefault(); const selection = frameDocument.defaultView.getSelection(); if (!selection.rangeCount) return;
            const range = selection.getRangeAt(0); range.deleteContents(); const line = frameDocument.createElement('br'); range.insertNode(line); range.setStartAfter(line); range.collapse(true); selection.removeAllRanges(); selection.addRange(range); captureText(live); changed();
        });
        canvasReady = true; $('#editor-canvas-loading').hidden = true;
        await renderFrame();
    }
    let renderTimer;
    async function renderFrame(override = null, revisionId = null) {
        if (!canvasReady) return;
        window.clearTimeout(renderTimer);
        const generation = ++rendering;
        renderTimer = window.setTimeout(async () => {
            try {
                const parameters = {payload:JSON.stringify(override || currentPayload(true))};
                if (revisionId) parameters.revision_id = revisionId;
                const data = await command('render', parameters);
                if (generation !== rendering) return;
                if (!config.styleOnly) frameRoot.innerHTML = data.html;
                let advancedStyle = frameDocument.getElementById('builder-preview-styles');
                if (!advancedStyle) { advancedStyle = frameDocument.createElement('style'); advancedStyle.id = 'builder-preview-styles'; frameDocument.head.append(advancedStyle); }
                advancedStyle.textContent = data.css || '';
                advanced?.rendered(frameDocument); blogEditorial?.rendered();
                updateArticleFrame();
                if (selected && !readOnly) frameRoot.querySelector('[data-cms-node="' + selected + '"]')?.setAttribute('data-editor-selected','true');
            } catch (error) { notice(error.message, true); }
        }, 180);
    }
    function history() {
        $('#editor-revisions').innerHTML = state.revisions.length ? state.revisions.map(revision => `<article class="editor-revision"><strong>${escape(String(revision.action).replaceAll('_',' '))}</strong><small>${escape(revision.created_at)} · ${escape(revision.created_by)}</small><div><button data-revision-view="${revision.revision_id}">View</button><button data-revision-restore="${revision.revision_id}">Restore as draft</button></div></article>`).join('') : '<p class="editor-hint">Your first saved draft will create the initial revision history.</p>';
    }
    async function saveDraft(options = {}) {
        if (saving) throw new Error('A draft save is already in progress. Please wait.');
        const manageBusy = !busy;
        if (manageBusy) setBusy(true);
        saving = true;
        const outgoing = JSON.stringify(currentPayload());
        try {
            const data = await command('save', {payload:outgoing});
            const newerEdits = JSON.stringify(currentPayload()) !== outgoing;
            state = data.state; dirty = newerEdits;
            if (!newerEdits) {
                window.clearTimeout(recoveryTimer);
                try { sessionStorage.removeItem(recoveryKey); } catch (_) {}
                if (options.preserveCanvas) payload = {...state.payload}; else loadSource(state.payload);
            } else keepRecovery();
            history(); status(newerEdits ? 'Draft saved · newer edits pending' : 'Draft saved');
            if (!options.quiet) notice(newerEdits ? 'The draft was saved. Your newer edits remain in this tab and still need saving.' : 'Draft saved. Visitors continue to see the published version.');
            if (!options.preserveCanvas && !newerEdits) renderFrame();
        } finally { saving = false; if (manageBusy) setBusy(false); }
    }
    async function run(callback) { if (busy) return; setBusy(true); notice(''); try { await callback(); } catch (error) { notice(error.message, true); } finally { setBusy(false); } }
    function keyboardSave(event) { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); run(saveDraft); } }
    document.addEventListener('keydown', keyboardSave);
    $('#editor-save').addEventListener('click', () => run(saveDraft));
    $('#editor-publish').addEventListener('click', () => run(async () => {
        if (!window.confirm('Publish this page? These changes will become visible to website visitors.')) return;
        if (dirty || !state.version) await saveDraft();
        if (dirty) throw new Error('New edits were made during saving. Save the current draft before publishing.');
        const publishing = JSON.stringify(currentPayload()); const data = await command('publish'); const newerEdits = JSON.stringify(currentPayload()) !== publishing; state = data.state; dirty = newerEdits; if (newerEdits) keepRecovery(); else loadSource(state.payload); history(); status(newerEdits ? 'Published · newer edits pending' : 'Published'); notice(newerEdits ? 'The saved version was published. Your newer edits remain private in this tab.' : 'Published. The website cache has been refreshed.'); if (!newerEdits) renderFrame();
    }));
    $('#editor-preview').addEventListener('click', () => {
        if (busy) return;
        const previewWindow = window.open('about:blank', '_blank'); if (previewWindow) previewWindow.opener = null;
        run(async () => { try { if (dirty || !state.version) await saveDraft(); if (dirty) throw new Error('Save the newer edits before opening a preview.'); const data = await command('preview'); if (previewWindow) previewWindow.location.href = data.url; else notice('Your browser blocked the preview tab. Allow pop-ups for this website and try again.'); } catch (error) { previewWindow?.close(); throw error; } });
    });
    host.querySelectorAll('[data-meta]').forEach(input => input.addEventListener('input', () => { payload[input.dataset.meta] = input.value; changed(); updateArticleFrame(); }));
    host.querySelectorAll('[data-article]').forEach(input => input.addEventListener(input.multiple || input.type === 'checkbox' ? 'change' : 'input', () => { payload[input.dataset.article] = input.multiple ? [...input.selectedOptions].map(option => Number(option.value)) : input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value; changed(); updateArticleFrame(); }));
    $('#article-choose-image')?.addEventListener('click', () => openMedia('featured'));
    $('#article-remove-image')?.addEventListener('click', () => { payload.featured_image = ''; featuredPreview(); changed(); });
    host.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => { host.querySelectorAll('[data-tab]').forEach(item => item.classList.toggle('active', item === button)); host.querySelectorAll('.editor-tab').forEach(panel => { panel.hidden = panel.id !== 'editor-' + button.dataset.tab; }); }));
    host.querySelectorAll('[data-device]').forEach(button => button.addEventListener('click', () => { host.querySelectorAll('[data-device]').forEach(item => item.classList.toggle('active', item === button)); $('#editor-frame').dataset.device = button.dataset.device; advanced?.device(button.dataset.device); }));
    $('#editor-view').addEventListener('click', () => { readOnly = !readOnly; $('#editor-view').textContent = readOnly ? 'Return to editing' : 'View only'; $('#editor-view-label').textContent = readOnly ? 'Preview — links disabled' : 'Editing draft'; inspector(); renderFrame(); });
    $('#editor-navigation').addEventListener('change', event => { if (!dirty || window.confirm('Leave this page without saving your changes?')) window.location.href = event.target.value; });
    window.addEventListener('beforeunload', event => { if (dirty) { keepRecovery(); event.preventDefault(); event.returnValue = ''; } });
    $('#editor-section-list').addEventListener('click', event => {
        const button = event.target.closest('button'); if (!button) return;
        if (button.dataset.pick) { selectNode(button.dataset.pick); frameDocument?.querySelector('[data-cms-node="' + button.dataset.pick + '"]')?.scrollIntoView({behavior:'smooth',block:'center'}); return; }
        const key = button.dataset.key || button.dataset.duplicate || button.dataset.delete, element = canonicalNode(key); if (!element) return;
        if (button.dataset.move) { const items = sections(), index = items.indexOf(element), sibling = items[index + (button.dataset.move === 'up' ? -1 : 1)]; if (!sibling) return; if (button.dataset.move === 'up') sibling.before(element); else sibling.after(element); }
        if (button.dataset.duplicate) { const clone = element.cloneNode(true); clone.querySelectorAll('[data-cms-node]').forEach(node => node.removeAttribute('data-cms-node')); clone.removeAttribute('data-cms-node'); advanced?.cloneIds(clone); element.after(clone); assignNodes(); }
        if (button.dataset.delete) { if (!window.confirm('Remove this section from the draft?')) return; element.remove(); selected = null; }
        changed(); drawSections(); inspector(); renderFrame();
    });
    let dragging;
    $('#editor-section-list').addEventListener('dragstart', event => { dragging = event.target.closest('[data-section]')?.dataset.section; event.dataTransfer.setData('text/plain', dragging || ''); });
    $('#editor-section-list').addEventListener('dragover', event => event.preventDefault());
    $('#editor-section-list').addEventListener('drop', event => { event.preventDefault(); const target = event.target.closest('[data-section]')?.dataset.section; if (dragging && target && dragging !== target) { canonicalNode(target).before(canonicalNode(dragging)); changed(); drawSections(); renderFrame(); } });
    $('#editor-revisions').addEventListener('click', event => {
        const button = event.target.closest('button'); if (!button) return;
        run(async () => {
            const id = button.dataset.revisionView || button.dataset.revisionRestore;
            if (button.dataset.revisionView) {
                const data = await command('revision', {revision_id:id}); readOnly = true; $('#editor-view').textContent = 'Return to editing'; $('#editor-view-label').textContent = 'Viewing revision ' + id; inspector(); renderFrame(data.payload, id);
            } else {
                if (!window.confirm('Restore this revision as the working draft? The published page stays unchanged.')) return;
                const data = await command('restore', {revision_id:id}); state = data.state; readOnly = false; dirty = false; loadSource(state.payload); history(); status('Revision restored as draft'); $('#editor-view-label').textContent = 'Editing draft'; $('#editor-view').textContent = 'View only'; notice('Revision restored as a draft. Review it before publishing.'); renderFrame();
            }
        });
    });
    const textBlock = html => config.pageBuilder ? `<div data-content-type="text" data-appearance="default" data-element="main">${html}</div>` : html;
    function template(type) {
        if (type === 'blog' && config.blogWidget && blogControls) return `<div class="shi3ld-pb-row" data-content-type="row" data-appearance="full-width" data-element="main" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image"><div class="row-full-width-inner" data-element="inner"><div data-content-type="html" data-appearance="default" data-element="main"><section class="container" data-editor-widget="blog-articles">${blogControls.directive(config.blogWidget,{})}</section></div></div></div>`;
        const image = config.pageBuilder ? '{{media url=wysiwyg/shi3ld/home-sample.jpg}}' : '/services/assets/home-sample.jpg';
        const heading = textBlock('<h2>Your section heading</h2><p>Add helpful information for your visitors.</p>');
        const button = config.pageBuilder ? '<div class="shi3ld-pb-buttons" data-content-type="buttons" data-appearance="inline" data-element="main"><div data-content-type="button-item" data-appearance="default" data-element="main"><a class="pagebuilder-button-primary" href="/services/quote" data-element="link" data-link-type="default"><span data-element="link_text">Get in touch ↗</span></a></div></div>' : '<a class="button" href="/services/quote">Get in touch ↗</a>';
        const imageHtml = `<figure ${config.pageBuilder ? 'data-content-type="image" data-appearance="full-width" data-element="main"' : ''}><img ${config.pageBuilder ? 'data-element="desktop_image"' : ''} src="${image}" alt="Illustrative website image" style="max-width:100%;height:auto"></figure>`;
        const columns = (a,b) => config.pageBuilder ? `<div class="pagebuilder-column-group shi3ld-pb-split" data-content-type="column-group" data-appearance="default" data-grid-size="12" data-element="main"><div class="pagebuilder-column-line" data-content-type="column-line" data-appearance="default" data-grid-size="12" data-element="main" style="display:flex;width:100%">${[a,b].map(item => `<div class="pagebuilder-column" data-content-type="column" data-appearance="full-height" data-element="main" style="display:flex;flex-direction:column;width:50%">${item}</div>`).join('')}</div></div>` : `<div class="split-section">${[a,b].map(item=>`<div>${item}</div>`).join('')}</div>`;
        const content = type === 'image' ? columns(heading + button, imageHtml) : type === 'columns' ? columns(heading, textBlock('<h3>Another perspective</h3><p>Add your supporting information here.</p>')) : type === 'cta' ? heading + button : type === 'faq' ? heading + textBlock('<details><summary>Your question</summary><p>Your helpful answer.</p></details>') : heading;
        return config.pageBuilder ? `<div class="shi3ld-pb-row" data-content-type="row" data-appearance="full-width" data-element="main" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image"><div class="row-full-width-inner" data-element="inner">${content}</div></div>` : `<section class="section container">${content}</section>`;
    }
    $('#editor-add').addEventListener('click', () => $('#editor-add-dialog').showModal());
    host.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => $('#' + button.dataset.closeDialog).close()));
    host.querySelectorAll('[data-template]').forEach(button => button.addEventListener('click', () => { source.insertAdjacentHTML('beforeend', template(button.dataset.template)); assignNodes(); changed(); drawSections(); renderFrame(); $('#editor-add-dialog').close(); }));
    let media = [];
    function drawMedia() { $('#editor-media-grid').innerHTML = media.length ? media.map((image,index) => `<button data-media="${index}"><img src="${escape(image.url)}" alt="" loading="lazy"><span>${escape(image.name)}</span></button>`).join('') : '<p>No images yet. Upload an image to get started.</p>'; }
    async function openMedia(target = 'node') { if (typeof target !== 'function') mediaCallback = null; if (typeof target === 'function') { mediaCallback = target; target = 'callback'; } mediaTarget = typeof target === 'string' ? target : 'node'; $('#editor-media-dialog').showModal(); try { const data = await command('media'); media = data.media; drawMedia(); } catch (error) { notice(error.message, true); } }
    function featuredPath() { return String(payload.featured_image || '').replace(/^\{\{media\s+url=["']?([^"'}]+)["']?\}\}$/, '$1'); }
    function updateArticleFrame() {
        if (!config.article || !frameDocument) return;
        const header = frameDocument.querySelector('.blog-article .blog-hero'); if (!header) return;
        const title = header.querySelector('h1'); if (title) title.textContent = payload.title;
        let introduction = header.querySelector('.blog-introduction');
        if (!introduction && payload.excerpt) { introduction = frameDocument.createElement('p'); introduction.className = 'blog-introduction'; header.append(introduction); }
        if (introduction) { introduction.textContent = payload.excerpt || ''; introduction.hidden = !payload.excerpt; }
        const image = frameDocument.querySelector('.blog-featured-image img');
        if (image) { image.src = featuredPath() ? '/media/' + featuredPath().split('/').map(encodeURIComponent).join('/') : '/services/assets/blog-placeholder.svg'; image.removeAttribute('srcset'); image.alt = payload.featured_alt || payload.title; }
        const categories = header.querySelector('.blog-categories');
        if (categories) { categories.replaceChildren(); config.categories.filter(category => Number(category.is_active) && (payload.category_ids || []).map(Number).includes(Number(category.category_id))).forEach(category => { const link = frameDocument.createElement('a'); link.href = '/blog/category/' + category.slug; link.textContent = category.title; categories.append(link); }); }
    }
    function featuredPreview() { const image = $('#article-image-preview'); if (!image) return; const path = featuredPath(); image.hidden = !path; if (path) image.src = '/media/' + path.split('/').map(encodeURIComponent).join('/'); updateArticleFrame(); }
    function chooseImage(image) { if (mediaTarget === 'callback' && mediaCallback) { const callback = mediaCallback; mediaCallback = null; callback(image); $('#editor-media-dialog').close(); return; } if (mediaTarget === 'featured') { payload.featured_image = image.source; featuredPreview(); changed(); $('#editor-media-dialog').close(); return; } let element = canonicalNode(selected); if (!element) return; if (element.tagName.toLowerCase() === 'svg') { const replacement = document.createElement('img'); replacement.setAttribute('data-cms-node', selected); replacement.setAttribute('alt','Website brand mark'); replacement.setAttribute('style','width:35px;height:41px;object-fit:contain'); element.replaceWith(replacement); element = replacement; } if (element.tagName !== 'IMG') return; const figure = element.closest('[data-content-type="image"]'); (figure ? figure.querySelectorAll('img') : [element]).forEach(item => item.setAttribute('src', image.source)); if(element.closest('[data-builder-widget="gallery"]')) element.closest('a')?.setAttribute('href',image.source); changed(); renderFrame(); inspector(); $('#editor-media-dialog').close(); }
    $('#editor-media-grid').addEventListener('click', event => { const button = event.target.closest('[data-media]'); if (button) chooseImage(media[Number(button.dataset.media)]); });
    $('#editor-upload').addEventListener('change', async event => { const file = event.target.files[0]; if (!file) return; try { const data = await command('upload', {image:file}); media.unshift(data.image); drawMedia(); chooseImage(data.image); } catch (error) { notice(error.message, true); } finally { event.target.value = ''; } });
    const editorApi = {config,host, source:()=>source, selected:()=>selected && canonicalNode(selected), frame:()=>frameDocument, getPayload:()=>currentPayload(), updatePayload:value=>{payload={...payload,...value};}, setPayload:value=>{loadSource(value);changed();renderFrame();}, changed, render:renderFrame, inspect:inspector, select:element=>selectNode(element.dataset.cmsNode), refresh:()=>{assignNodes();changed();drawSections();inspector();renderFrame();}, command, notice, save:saveDraft, chooseMedia:openMedia};
    editorApi.getState = () => state; editorApi.isDirty = () => dirty; editorApi.isBusy = () => busy || saving; editorApi.isReadOnly = () => readOnly;
    editorApi.autosave = () => run(() => saveDraft({preserveCanvas:true,quiet:true}));
    advanced = window.Shi3ldBuilderAdvanced?.mount(editorApi);
    blogEditorial = window.Shi3ldBlogEditorial?.mount(editorApi);
    loadSource(payload);
    try { const recovery = JSON.parse(sessionStorage.getItem(recoveryKey) || 'null'); if (recovery?.payload && JSON.stringify(recovery.payload) !== JSON.stringify(payload) && window.confirm(recovery.version === state.version ? 'Recover unsaved changes from this browser tab?' : 'A newer saved draft exists. Recover the unsaved changes from this tab as your working copy? Review History before saving.')) { loadSource(recovery.payload); dirty = true; status('Recovered unsaved changes'); } } catch (_) {}
    history(); initializeCanvas().catch(error => { $('#editor-canvas-loading').textContent = error.message; notice(error.message, true); });
}());
