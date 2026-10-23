(function () {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
    const field = (label, control) => '<label class="editor-field">' + escape(label) + control + '</label>';
    const imagePath = item => String(item.source || item.url || '').replace(/^\{\{media\s+url\s*=\s*["']?/, '').replace(/["']?\}\}$/, '').replace(/^\/media\//, '');
    window.Shi3ldBlogEditorial = {
        mount(api) {
            const options = api.config.blogEditorial;
            if (!options) return null;
            const host = api.host, tab = document.createElement('button'), panel = document.createElement('section');
            tab.dataset.tab = 'blog-editorial'; tab.textContent = 'Blog';
            panel.id = 'editor-blog-editorial'; panel.className = 'editor-tab blog-editorial'; panel.hidden = true;
            host.querySelector('.editor-tabs').append(tab); host.querySelector('.editor-sidebar').append(panel);
            let data, lease = null, lastChange = Date.now(), noteDraft = '', versionConflict = false, changedSinceLoad = false;
            const dialog = document.createElement('dialog'); dialog.className = 'blog-editor-dialog'; host.append(dialog);
            const $ = selector => panel.querySelector(selector);
            const metadata = () => ({...options.defaults,...(api.getPayload().editorial || {})});
            const changeMetadata = next => { api.updatePayload({editorial:next}); api.changed(); syncPreview(); };
            async function command(intent, extra = {}, keepalive = false) {
                const body = new FormData(); body.set('form_key', api.config.csrf); body.set('intent', intent);
                body.set('data', JSON.stringify({entity_type:options.type, id:options.id,...extra}));
                const response = await fetch(options.url, {method:'POST', credentials:'same-origin', redirect:'error', body, keepalive, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                let result; try { result = await response.json(); } catch (_) { throw new Error('Your session may have expired. Keep this tab open and sign in again.'); }
                if (!response.ok || result.error) throw new Error(result.error || 'The blog action could not be completed.');
                return result;
            }
            function input(name, value, type = 'text') { return '<input data-blog-field="' + name + '" type="' + type + '" value="' + escape(value) + '">'; }
            function select(name, rows, key, label, value, multiple = false) {
                return '<select data-blog-field="' + name + '" ' + (multiple ? 'multiple size="6"' : '') + '>' + (multiple ? '' : '<option value="0">None</option>') + rows.map(row => '<option value="' + row[key] + '" ' + ((multiple ? value.map(Number).includes(Number(row[key])) : Number(value) === Number(row[key])) ? 'selected' : '') + '>' + escape(row[label]) + '</option>').join('') + '</select>';
            }
            function showDialog(title, content) {
                dialog.innerHTML = '<div class="dialog-heading"><h2>' + escape(title) + '</h2><button type="button" data-close aria-label="Close">×</button></div>' + content;
                dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
                if (!dialog.open) dialog.showModal();
            }
            function presence() {
                const target = $('[data-blog-presence]'); if (!target) return;
                target.textContent = versionConflict ? 'A newer saved version exists. Reload after preserving your unsaved work.' : !lease ? 'Connecting to the editor…' : lease.owned ? 'Editing as ' + lease.actor + ' · autosave after a pause, checked every 30 seconds' : lease.actor + ' is editing this article. Your changes will stay in this tab until their lease ends.';
                target.classList.toggle('blog-presence-warning', versionConflict || Boolean(lease && !lease.owned));
            }
            function paint() {
                if (!api.source()) return;
                if (!data) { panel.innerHTML = '<h2>Article tools</h2><p>Loading editorial settings…</p>'; return; }
                const value = metadata();
                panel.innerHTML = '<h2>Article tools</h2><p data-blog-presence role="status"></p>' + (api.config.canPublish ? '<button type="button" data-takeover>Take over editing lease</button>' : '') + '<p><a href="' + escape(options.managementUrl) + '" target="_blank" rel="noopener">Open blog management ↗</a></p>' +
                    '<details open><summary>Author and topics</summary>' + field('Author profile', select('author_id', data.authors, 'author_id', 'name', value.author_id)) + field('Topics / tags', select('tag_ids', data.tags, 'tag_id', 'name', value.tag_ids, true)) + '</details>' +
                    '<details><summary>Related articles</summary><p class="editor-hint">Recommendations also use matching categories and tags. Only published articles appear to readers.</p><div data-pins></div>' + field('Exclude articles', select('excluded_ids', data.articles.filter(row => row.id !== data.base_id), 'id', 'title', value.excluded_ids, true)) + '</details>' +
                    '<details><summary>Products and services</summary><button type="button" data-products>Choose Magento products</button><details><summary>Selected product SKUs</summary>' + field('One SKU per line', '<textarea data-blog-field="product_skus" rows="5">' + escape(value.product_skus.join('\n')) + '</textarea>') + '</details><div data-services></div><button type="button" data-add-service>Add service link</button></details>' +
                    '<details><summary>Reader experience</summary>' + ['show_toc','show_progress','allow_comments'].map((name,index) => '<label class="editor-field blog-check"><input type="checkbox" data-blog-field="' + name + '" ' + (value[name] ? 'checked' : '') + '> ' + ['Automatic table of contents','Reading progress bar','Allow moderated comments'][index] + '</label>').join('') + '<p class="editor-hint">Comments also need enabling in Blog Management. Reading time is calculated automatically.</p></details>' +
                    '<details><summary>Search and social sharing</summary><p class="editor-hint">SEO title and description remain in the Page tab. These overrides control sharing and canonical links.</p>' + field('Social title', input('social_title', value.social_title)) + field('Social description', '<textarea data-blog-field="social_description" rows="4">' + escape(value.social_description) + '</textarea>') + '<button type="button" data-social-image>Choose sharing image</button><button type="button" data-clear-social>Use featured image</button>' + (value.social_image ? '<img class="blog-editor-image" src="/media/' + escape(value.social_image) + '" alt="Sharing image preview">' : '') + field('Canonical override (optional)', input('canonical', value.canonical)) + '<button type="button" data-seo>Review SEO and sharing preview</button><div data-seo-report></div></details>' +
                    '<details><summary>Featured image caption and credit</summary>' + field('Caption', input('image_caption', value.image_caption)) + field('Image credit', input('image_credit', value.image_credit)) + '</details>' +
                    '<details><summary>Assignment and review dates</summary>' + field('Assigned editor', select('assigned_to', data.staff, 'user_id', 'username', value.assigned_to)) + field('Draft deadline (UTC)', input('due_at', value.due_at, 'date')) + field('Content review date (UTC)', input('review_at', value.review_at, 'date')) + field('Retirement date (00:00 UTC)', input('retire_at', value.retire_at, 'date')) + '<p class="editor-hint">A published article is hidden from midnight UTC on this date. The retirement setting becomes active when published.</p></details>' +
                    '<details><summary>Article starter layouts</summary><p class="editor-hint">Insert a starter at the end of the working draft, then replace the prompts with approved content.</p>' + Object.entries(options.starters).map(([key,item]) => '<button type="button" data-starter="' + key + '" ' + (api.config.canDesign === false ? 'disabled' : '') + '>' + escape(item.title) + '</button>').join('') + '</details>' +
                    '<details><summary>Internal editorial notes</summary><div data-notes></div><label class="editor-field">New note<textarea data-note rows="4" maxlength="3000">' + escape(noteDraft) + '</textarea></label><button type="button" data-add-note>Add internal note</button></details>';
                presence(); drawPins(); drawServices(); drawNotes();
            }
            function drawPins() {
                const root = $('[data-pins]'); if (!root || !data) return;
                const pins = metadata().pinned_ids;
                root.innerHTML = '<ol class="blog-pins">' + pins.map((id,index) => '<li>' + escape(data.articles.find(row => row.id === Number(id))?.title || 'Previously selected article') + '<div><button type="button" data-pin-move="' + index + '" data-direction="-1" aria-label="Move recommendation up">↑</button><button type="button" data-pin-move="' + index + '" data-direction="1" aria-label="Move recommendation down">↓</button><button type="button" data-pin-remove="' + index + '">Remove</button></div></li>').join('') + '</ol><label class="editor-field">Pin an article<select data-pin-choice><option value="">Choose an article</option>' + data.articles.filter(row => row.id !== data.base_id && !pins.map(Number).includes(row.id)).map(row => '<option value="' + row.id + '">' + escape(row.title) + '</option>').join('') + '</select></label><button type="button" data-pin-add>Add pinned recommendation</button>';
            }
            function drawServices() {
                const root = $('[data-services]'); if (!root) return;
                root.innerHTML = metadata().services.map((item,index) => '<fieldset data-service-index="' + index + '"><legend>Service link ' + (index + 1) + '</legend>' + field('Button label', '<input data-service-part="label" value="' + escape(item.label) + '" maxlength="150">') + field('Service destination', '<input data-service-part="url" value="' + escape(item.url) + '" placeholder="/services/domestic-cleaning">') + '<button type="button" data-remove-service="' + index + '">Remove link</button></fieldset>').join('');
            }
            function drawNotes() {
                const root = $('[data-notes]'); if (!root || !data) return;
                root.innerHTML = data.notes.map(note => '<article class="blog-editor-note"><strong>' + escape(note.actor) + '</strong><small>' + escape(note.created_at) + ' UTC</small><p>' + escape(note.body) + '</p></article>').join('') || '<p>No internal notes yet.</p>';
            }
            function updateField(event) {
                const control = event.target, name = control.dataset.blogField;
                if (control.matches('[data-note]')) { noteDraft = control.value; return; }
                if (control.dataset.servicePart) { const next = metadata(), index = Number(control.closest('[data-service-index]').dataset.serviceIndex); next.services[index][control.dataset.servicePart] = control.value; changeMetadata(next); return; }
                if (!name || (event.type === 'input' && (control.tagName === 'SELECT' || control.type === 'checkbox')) || (event.type === 'change' && control.tagName !== 'SELECT' && control.type !== 'checkbox')) return;
                const next = metadata();
                next[name] = control.multiple ? [...control.selectedOptions].map(option => Number(option.value)) : control.type === 'checkbox' ? control.checked : ['author_id','assigned_to'].includes(name) ? Number(control.value) : name === 'product_skus' ? control.value.split('\n').map(value => value.trim()).filter(Boolean) : control.value;
                if (name === 'author_id' && next.author_id) { const author = data.authors.find(row => Number(row.author_id) === next.author_id); if (author) { api.updatePayload({author:author.name}); const original = host.querySelector('[data-article="author"]'); if (original) original.value = author.name; } }
                changeMetadata(next);
            }
            panel.addEventListener('input', updateField); panel.addEventListener('change', updateField);
            panel.addEventListener('click', async event => {
                const button = event.target.closest('button'); if (!button) return;
                event.preventDefault();
                try {
                    if (button.hasAttribute('data-takeover')) { if (confirm('Take over this article’s editing lease? The other editor will need to preserve their unsaved work.')) { const result = await command('heartbeat', {takeover:true}); lease = result.presence; versionConflict = Number(result.version) > Number(api.getState().version); presence(); } }
                    if (button.hasAttribute('data-pin-add')) { const id = Number($('[data-pin-choice]').value); if (id) { const next = metadata(); next.pinned_ids = [...new Set([...next.pinned_ids,id])]; changeMetadata(next); drawPins(); } }
                    if (button.dataset.pinRemove !== undefined) { const next = metadata(); next.pinned_ids.splice(Number(button.dataset.pinRemove),1); changeMetadata(next); drawPins(); }
                    if (button.dataset.pinMove !== undefined) { const next = metadata(), index = Number(button.dataset.pinMove), target = index + Number(button.dataset.direction); if (target >= 0 && target < next.pinned_ids.length) { [next.pinned_ids[index],next.pinned_ids[target]] = [next.pinned_ids[target],next.pinned_ids[index]]; changeMetadata(next); drawPins(); } }
                    if (button.hasAttribute('data-add-service')) { const next = metadata(); if (next.services.length >= 12) throw new Error('An article can contain up to 12 service links.'); next.services = [...next.services,{label:'',url:'/services/'}]; changeMetadata(next); drawServices(); }
                    if (button.dataset.removeService !== undefined) { const next = metadata(); next.services.splice(Number(button.dataset.removeService),1); changeMetadata(next); drawServices(); }
                    if (button.hasAttribute('data-social-image')) api.chooseMedia(item => { const next = metadata(); next.social_image = imagePath(item); changeMetadata(next); paint(); });
                    if (button.hasAttribute('data-clear-social')) { const next = metadata(); next.social_image = ''; changeMetadata(next); paint(); }
                    if (button.hasAttribute('data-add-note')) { button.disabled = true; const result = await command('note', {body:noteDraft}); data.notes = result.notes; noteDraft = ''; $('[data-note]').value = ''; drawNotes(); }
                    if (button.dataset.starter) { if (api.config.canDesign === false) return; const wrapper = document.createElement('div'); wrapper.innerHTML = options.starters[button.dataset.starter].html; api.source().append(...wrapper.childNodes); api.refresh(); api.notice('Starter inserted into the working draft. Replace the prompts before publishing.'); }
                    if (button.hasAttribute('data-seo')) { button.disabled = true; const result = await command('seo', {payload:JSON.stringify(api.getPayload())}); const report = result.report; $('[data-seo-report]').innerHTML = '<p>' + report.reading.words + ' words · about ' + report.reading.minutes + ' min read</p><div class="blog-search-preview"><strong>' + escape(report.preview.title) + '</strong><small>' + escape(report.preview.url) + '</small><p>' + escape(report.preview.description) + '</p></div><h3>Sharing preview</h3>' + (report.preview.social_image ? '<img class="blog-editor-image" src="/media/' + escape(report.preview.social_image) + '" alt="Sharing preview">' : '') + '<strong>' + escape(report.preview.social_title) + '</strong><p>' + escape(report.preview.social_description) + '</p><ul>' + report.issues.map(issue => '<li>' + escape(issue.message) + '</li>').join('') + '</ul>'; }
                    if (button.hasAttribute('data-products')) await chooseProducts();
                } catch (error) { api.notice(error.message,true); }
                finally { button.disabled = false; }
            });
            async function chooseProducts() {
                const chosen = new Set(metadata().product_skus);
                showDialog('Choose related products', '<label class="editor-field">Search products<input type="search" data-product-search></label><div data-product-results>Loading…</div><button type="button" data-product-apply>Add selected products</button>');
                let generation = 0, timer;
                async function search() {
                    const current = ++generation, result = await command('products', {search:dialog.querySelector('[data-product-search]').value});
                    if (current !== generation) return;
                    dialog.querySelector('[data-product-results]').innerHTML = result.products.map(product => '<label class="blog-product-choice"><input type="checkbox" value="' + escape(product.sku) + '" ' + (chosen.has(product.sku) ? 'checked' : '') + '> ' + escape(product.name) + ' <small>' + escape(product.sku) + '</small></label>').join('') || '<p>No matching products.</p>';
                }
                dialog.querySelector('[data-product-search]').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => search().catch(error => api.notice(error.message,true)),300); });
                dialog.querySelector('[data-product-results]').addEventListener('change', event => { if (event.target.checked) chosen.add(event.target.value); else chosen.delete(event.target.value); });
                dialog.querySelector('[data-product-apply]').addEventListener('click', () => { if (chosen.size > 24) { api.notice('Choose up to 24 products.',true); return; } const next = metadata(); next.product_skus = [...chosen]; changeMetadata(next); dialog.close(); paint(); });
                await search();
            }
            function syncPreview() {
                const frame = api.frame(); if (!frame || !api.source()) return;
                const value = metadata(), figure = frame.querySelector('.blog-featured-image');
                if (figure) { let caption = figure.querySelector('figcaption'); if (!caption && (value.image_caption || value.image_credit)) { caption = frame.createElement('figcaption'); figure.append(caption); } if (caption) caption.textContent = [value.image_caption,value.image_credit ? 'Image credit: ' + value.image_credit : ''].filter(Boolean).join(' · '); }
            }
            tab.addEventListener('click', () => { host.querySelectorAll('[data-tab]').forEach(item => item.classList.toggle('active',item === tab)); host.querySelectorAll('.editor-tab').forEach(item => { item.hidden = item !== panel; }); paint(); });
            async function heartbeat() {
                if (document.hidden) return;
                try { const result = await command('heartbeat'); lease = result.presence; versionConflict = Number(result.version) > Number(api.getState().version); presence(); }
                catch (error) { lease = null; presence(); if (changedSinceLoad) api.notice(error.message,true); }
            }
            command('editor').then(result => { data = result; lease = result.presence; versionConflict = Number(result.version) > Number(api.getState().version); if (!panel.hidden) paint(); }).catch(error => api.notice(error.message,true));
            setInterval(heartbeat,30000);
            setInterval(() => { if (!document.hidden && lease?.owned && !versionConflict && api.isDirty() && !api.isBusy() && !api.isReadOnly() && Date.now() - lastChange > 5000) api.autosave(); },30000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) heartbeat(); });
            addEventListener('pagehide', () => { command('release',{},true).catch(() => {}); });
            return {changed(){lastChange = Date.now();changedSinceLoad = true;},loaded(){if (!panel.hidden) paint();},rendered:syncPreview};
        }
    };
}());
