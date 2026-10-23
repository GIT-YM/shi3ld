(function () {
    'use strict';
    const configNode = document.getElementById('blog-studio-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), host = document.getElementById('blog-studio');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
    const today = new Date().toISOString().slice(0,10);
    const selected = new Set(), known = new Map();
    let data, active = 'articles', page = 1, search = '', filterStatus = '', month = today.slice(0,7), calendar = [], generation = 0;
    const tabs = {articles:'Articles',calendar:'Calendar',authors:'Authors',tags:'Topics / tags',seo:'SEO & sharing',languages:'Languages',transfer:'Import / export',...(config.canPublish ? {feedback:'Feedback',analytics:'Analytics',subscriptions:'Subscriptions'} : {})};
    host.innerHTML = '<div class="blog-studio-top"><p>SHI3LD JOURNAL</p><div><a href="' + escape(config.articlesUrl) + '">Create or edit articles ↗</a><a href="' + escape(config.categoriesUrl) + '">Manage categories ↗</a><a href="' + escape(config.libraryUrl) + '">Design library ↗</a></div></div><p id="blog-studio-notice" role="status" hidden></p><nav class="blog-studio-tabs" role="tablist" aria-label="Blog management">' + Object.entries(tabs).map(([key,label]) => '<button type="button" role="tab" id="blog-studio-tab-' + key + '" data-tab="' + key + '" aria-controls="blog-studio-panel">' + label + '</button>').join('') + '</nav><section id="blog-studio-panel" role="tabpanel"></section>';
    const panel = host.querySelector('#blog-studio-panel'), dialog = document.createElement('dialog'), mediaDialog = document.createElement('dialog');
    dialog.className = mediaDialog.className = 'blog-studio-dialog'; host.append(dialog,mediaDialog);
    const notice = (message, error = false) => { const node = host.querySelector('#blog-studio-notice'); node.textContent = message; node.hidden = !message; node.className = error ? 'error' : ''; };
    const field = (label, control) => '<label>' + escape(label) + control + '</label>';
    const input = (name, value = '', type = 'text', extra = '') => '<input name="' + name + '" type="' + type + '" value="' + escape(value) + '" ' + extra + '>';
    const options = (rows, key, label, value = '') => rows.map(row => '<option value="' + row[key] + '" ' + (String(row[key]) === String(value) ? 'selected' : '') + '>' + escape(row[label]) + '</option>').join('');
    const table = (headings, rows) => '<div class="blog-studio-table"><table><thead><tr>' + headings.map(heading => '<th scope="col">' + heading + '</th>').join('') + '</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
    const publicEditor = id => { const url = new URL(config.editorUrl,location.origin); url.searchParams.set('type','article'); url.searchParams.set('id',id); return url.href; };
    async function command(intent, values = {}, file = null) {
        const body = new FormData(); body.set('form_key',config.csrf); body.set('intent',intent); body.set('data',JSON.stringify(values));
        if (file) body.set(intent === 'upload' ? 'image' : 'package',file);
        const response = await fetch(config.url,{method:'POST',credentials:'same-origin',redirect:'error',body,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
        let answer; try { answer = await response.json(); } catch (_) { throw new Error('Your session may have expired. Sign in and reload this page.'); }
        if (!response.ok || answer.error) throw new Error(answer.error || 'This blog action could not be completed.');
        return answer;
    }
    function show(title, html, target = dialog) {
        target.innerHTML = '<div class="blog-studio-dialog-heading"><h2>' + escape(title) + '</h2><button type="button" data-close aria-label="Close">×</button></div>' + html;
        target.querySelector('[data-close]').addEventListener('click',() => target.close()); if (!target.open) target.showModal();
    }
    async function load() {
        const current = ++generation;
        const answer = await command('overview',{page,search,status:filterStatus});
        if (current !== generation) return;
        data = answer; data.articles.forEach(row => known.set(row.id,row)); await render();
    }
    async function chooseTab(name) { active = name; await render(); }
    async function render() {
        host.querySelectorAll('[data-tab]').forEach(button => { const chosen = button.dataset.tab === active; button.setAttribute('aria-selected',String(chosen)); button.tabIndex = chosen ? 0 : -1; });
        panel.setAttribute('aria-labelledby','blog-studio-tab-' + active);
        if (!data) { panel.innerHTML = '<p>Loading…</p>'; return; }
        panel.innerHTML = '<p>Loading ' + tabs[active].toLowerCase() + '…</p>';
        if (active === 'articles') articles();
        if (active === 'calendar') await drawCalendar();
        if (active === 'authors') taxonomy('author');
        if (active === 'tags') taxonomy('tag');
        if (active === 'seo') seo();
        if (active === 'languages') languages();
        if (active === 'transfer') transfer();
        if (active === 'feedback') await feedback();
        if (active === 'analytics') await analytics();
        if (active === 'subscriptions') await subscriptions();
    }
    host.querySelector('.blog-studio-tabs').addEventListener('click',event => { const name = event.target.closest('[data-tab]')?.dataset.tab; if (name) chooseTab(name).catch(error => notice(error.message,true)); });
    host.querySelector('.blog-studio-tabs').addEventListener('keydown',event => {
        if (!['ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
        event.preventDefault(); const keys = Object.keys(tabs), index = keys.indexOf(active);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? keys.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + keys.length) % keys.length;
        chooseTab(keys[next]).then(() => host.querySelector('[data-tab="' + keys[next] + '"]').focus()).catch(error => notice(error.message,true));
    });
    function articles() {
        const pages = Math.max(1,Math.ceil(data.total / 50));
        panel.innerHTML = '<div class="blog-studio-heading"><div><h2>Articles</h2><p>' + data.total + ' articles · ' + selected.size + ' selected (up to 50)</p></div><a class="blog-studio-button" href="' + escape(config.articlesUrl) + '">New article</a></div><form id="blog-list-filter" class="blog-studio-tools">' + field('Search',input('search',search,'search')) + field('Status','<select name="status"><option value="">All articles</option><option value="1" ' + (filterStatus === '1' ? 'selected' : '') + '>Published</option><option value="0" ' + (filterStatus === '0' ? 'selected' : '') + '>Unpublished</option></select>') + '<button type="submit">Apply filters</button></form><div class="blog-studio-tools"><button type="button" data-bulk="assign">Assign writer</button><button type="button" data-bulk="tags">Set topics</button>' + (config.canPublish ? '<button type="button" data-bulk="publish">Publish saved drafts</button><button type="button" data-bulk="unpublish">Unpublish</button>' : '') + '<button type="button" data-export-selected>Export selected</button></div>' +
            table(['<input type="checkbox" data-select-all aria-label="Select articles on this page">','Article','Publication','Writer / review','Open'],data.articles.map(row => '<tr><td><input type="checkbox" data-select="' + row.id + '" aria-label="Select ' + escape(row.title) + '" ' + (selected.has(row.id) ? 'checked' : '') + '></td><td><strong>' + escape(row.title) + '</strong><small>/blog/' + escape(row.slug) + '</small></td><td><span class="blog-badge">' + (row.published ? 'Published' : 'Unpublished') + '</span>' + (row.has_draft ? '<small>Draft changes</small>' : '') + (row.workflow && ['review','scheduled'].includes(row.workflow.state) ? '<small>' + escape(row.workflow.state) + (row.workflow.scheduled_at ? ' · ' + escape(row.workflow.scheduled_at) + ' UTC' : '') + '</small>' : '') + '</td><td>' + escape(data.staff.find(user => Number(user.user_id) === row.editorial.assigned_to)?.username || 'Unassigned') + (row.editorial.review_at ? '<small class="' + (row.editorial.review_at <= today ? 'blog-due' : '') + '">Review: ' + escape(row.editorial.review_at) + '</small>' : '') + '</td><td><a href="' + escape(row.url) + '">Visual editor ↗</a></td></tr>')) + '<div class="blog-studio-tools"><button type="button" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Previous</button><span>Page ' + page + ' of ' + pages + '</span><button type="button" data-page="' + (page + 1) + '" ' + (page >= pages ? 'disabled' : '') + '>Next</button></div>';
        panel.querySelector('#blog-list-filter').addEventListener('submit',event => { event.preventDefault(); const values = new FormData(event.target); search = values.get('search'); filterStatus = values.get('status'); page = 1; load().catch(error => notice(error.message,true)); });
        const updateSelection = () => {
            panel.querySelector('.blog-studio-heading p').textContent = data.total + ' articles · ' + selected.size + ' selected (up to 50)';
            const boxes = [...panel.querySelectorAll('[data-select]')], all = panel.querySelector('[data-select-all]');
            all.checked = boxes.length > 0 && boxes.every(box => box.checked);
            all.indeterminate = boxes.some(box => box.checked) && !all.checked;
        };
        panel.querySelectorAll('[data-select]').forEach(box => box.addEventListener('change',() => { const id = Number(box.dataset.select); if (box.checked) { if (selected.size >= 50) { box.checked = false; notice('Select up to 50 articles for one bulk operation.',true); updateSelection(); return; } selected.add(id); } else selected.delete(id); updateSelection(); }));
        panel.querySelector('[data-select-all]').addEventListener('change',event => { panel.querySelectorAll('[data-select]').forEach(box => { const id = Number(box.dataset.select); if (!event.target.checked) selected.delete(id); else if (selected.size < 50) selected.add(id); box.checked = selected.has(id); }); updateSelection(); });
        updateSelection();
        panel.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click',() => { page = Number(button.dataset.page); load().catch(error => notice(error.message,true)); }));
        panel.querySelectorAll('[data-bulk]').forEach(button => button.addEventListener('click',() => bulk(button.dataset.bulk)));
        panel.querySelector('[data-export-selected]').addEventListener('click',() => exportSelected().catch(error => notice(error.message,true)));
    }
    function bulk(action) {
        if (!selected.size) { notice('Select articles first.',true); return; }
        let content = '<p>Apply this action to ' + selected.size + ' selected articles.</p>';
        if (action === 'assign') content += field('Assigned writer','<select name="assigned_to"><option value="0">Unassigned</option>' + options(data.staff,'user_id','username') + '</select>');
        if (action === 'tags') content += '<fieldset><legend>Replace topic assignments with</legend>' + data.tags.map(tag => '<label class="blog-check"><input type="checkbox" name="tag_ids" value="' + tag.tag_id + '"> ' + escape(tag.name) + '</label>').join('') + '</fieldset>';
        show({assign:'Assign a writer',tags:'Set article topics',publish:'Publish saved drafts',unpublish:'Unpublish articles'}[action],'<form data-bulk-form>' + content + '<button type="submit">Apply ' + escape(action) + '</button></form>');
        dialog.querySelector('form').addEventListener('submit',async event => {
            event.preventDefault(); const button = event.target.querySelector('button'); button.disabled = true;
            try {
                const values = new FormData(event.target), versions = Object.fromEntries([...selected].map(id => [id,{version:known.get(id)?.version,live_hash:known.get(id)?.live_hash}]));
                const result = await command('bulk',{action,ids:[...selected],versions,assigned_to:Number(values.get('assigned_to') || 0),tag_ids:values.getAll('tag_ids').map(Number)});
                dialog.close(); selected.clear(); await load(); notice(result.message + (result.errors?.length ? '\n' + result.errors.map(error => (known.get(error.id)?.title || 'Article ' + error.id) + ': ' + error.message).join('\n') : ''),Boolean(result.errors?.length));
            } catch (error) { notice(error.message,true); } finally { button.disabled = false; }
        });
    }
    async function drawCalendar() {
        const requestedMonth = month; const response = await command('calendar',{month:requestedMonth}); if (active !== 'calendar' || requestedMonth !== month) return;
        calendar = response.articles; const events = [];
        calendar.forEach(row => {
            if (row.workflow?.state === 'scheduled' && row.workflow.scheduled_at) events.push({id:row.id,kind:'scheduled',date:row.workflow.scheduled_at.slice(0,10),label:'Publish'});
            [['due_at','Deadline'],['review_at','Review'],['retire_at','Retire']].forEach(([kind,label]) => { if (row.editorial[kind]) events.push({id:row.id,kind,date:row.editorial[kind],label}); });
        });
        const [year,number] = month.split('-').map(Number), start = new Date(Date.UTC(year,number - 1,1));
        start.setUTCDate(start.getUTCDate() - (start.getUTCDay() + 6) % 7);
        let cells = '';
        for (let index = 0; index < 42; index++) {
            const date = new Date(start); date.setUTCDate(date.getUTCDate() + index); const iso = date.toISOString().slice(0,10);
            cells += '<div class="blog-calendar-day ' + (iso.slice(0,7) !== month ? 'outside' : '') + (iso === today ? ' today' : '') + '" role="gridcell" data-calendar-date="' + iso + '" aria-label="' + iso + '"><strong>' + date.getUTCDate() + '</strong>' + events.filter(item => item.date === iso).map(item => { const row = calendar.find(post => post.id === item.id); return '<button type="button" class="blog-calendar-event ' + item.kind + '" draggable="' + (item.kind !== 'scheduled' || config.canPublish) + '" data-calendar-id="' + item.id + '" data-calendar-kind="' + item.kind + '"><small>' + item.label + '</small>' + escape(row.title) + '</button>'; }).join('') + '</div>';
        }
        panel.innerHTML = '<div class="blog-studio-heading"><div><h2>Editorial calendar</h2><p>Dates use UTC. Drag an event or open it to change its date.</p></div>' + field('Month',input('month',month,'month')) + '</div><div class="blog-calendar-scroll"><div class="blog-calendar" role="grid" aria-label="Editorial calendar">' + ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(day => '<div role="columnheader" class="blog-calendar-weekday">' + day + '</div>').join('') + cells + '</div></div>' + (!events.length ? '<p>No editorial dates are set for this month. Assign dates in an article’s Blog panel.</p>' : '') + (calendar.length >= response.limit ? '<p>The calendar shows the first ' + response.limit + ' matching articles.</p>' : '');
        panel.querySelector('[name=month]').addEventListener('change',event => { if (event.target.value) { month = event.target.value; drawCalendar().catch(error => notice(error.message,true)); } });
        panel.querySelectorAll('[data-calendar-id]').forEach(button => {
            button.addEventListener('click',() => editDate(Number(button.dataset.calendarId),button.dataset.calendarKind));
            button.addEventListener('dragstart',event => event.dataTransfer.setData('application/shi3ld-blog',JSON.stringify({id:Number(button.dataset.calendarId),kind:button.dataset.calendarKind})));
        });
        panel.querySelectorAll('[data-calendar-date]').forEach(cell => { cell.addEventListener('dragover',event => event.preventDefault()); cell.addEventListener('drop',event => { event.preventDefault(); try { const item = JSON.parse(event.dataTransfer.getData('application/shi3ld-blog')); editDate(item.id,item.kind,cell.dataset.calendarDate); } catch (_) {} }); });
    }
    function editDate(id, kind, date) {
        const row = calendar.find(item => item.id === id); if (!row) return;
        if (kind === 'scheduled' && !config.canPublish) { notice('Rescheduling requires publication permission.',true); return; }
        const original = kind === 'scheduled' ? row.workflow.scheduled_at.slice(0,10) : row.editorial[kind];
        show('Update editorial date','<p>' + escape(row.title) + '</p><form>' + field('Date (UTC)',input('date',date || original,'date','required')) + (kind === 'scheduled' ? field('Time (UTC)',input('time',row.workflow.scheduled_at.slice(11,16),'time','required')) : '') + '<p>' + (kind === 'scheduled' ? 'Reschedule saved version ' + row.version + '.' : 'The date will be saved in a draft. Publish retirement changes to activate them.') + '</p><button type="submit">Save date</button><a href="' + escape(row.url) + '">Open visual editor ↗</a></form>');
        dialog.querySelector('form').addEventListener('submit',async event => {
            event.preventDefault(); const values = new FormData(event.target), button = event.target.querySelector('button'); button.disabled = true;
            try { const result = await command(kind === 'scheduled' ? 'reschedule' : 'set_date',{id,version:row.version,live_hash:row.live_hash,field:kind,date:values.get('date'),scheduled_at:values.get('date') + 'T' + (values.get('time') || '00:00')}); dialog.close(); await drawCalendar(); notice(result.message || 'Publication rescheduled.'); }
            catch (error) { notice(error.message,true); } finally { button.disabled = false; }
        });
    }
    function taxonomy(kind) {
        const rows = kind === 'author' ? data.authors : data.tags, idKey = kind + '_id';
        panel.innerHTML = '<div class="blog-studio-heading"><h2>' + (kind === 'author' ? 'Author profiles' : 'Topics / tags') + '</h2>' + (config.canPublish ? '<button type="button" data-new-taxonomy>New ' + kind + '</button>' : '<p>Publishers manage these records.</p>') + '</div>' + table(['Name','URL','Details',...(config.canPublish ? ['Edit'] : [])],rows.map(row => '<tr><td>' + escape(row.name) + '</td><td>/blog/' + kind + '/' + escape(row.slug) + '</td><td>' + escape(kind === 'author' ? row.expertise + (row.is_active ? '' : ' · Hidden') : '') + '</td>' + (config.canPublish ? '<td><button type="button" data-edit-taxonomy="' + row[idKey] + '">Edit</button></td>' : '') + '</tr>'));
        panel.querySelector('[data-new-taxonomy]')?.addEventListener('click',() => taxonomyForm(kind));
        panel.querySelectorAll('[data-edit-taxonomy]').forEach(button => button.addEventListener('click',() => taxonomyForm(kind,rows.find(row => Number(row[idKey]) === Number(button.dataset.editTaxonomy)))));
    }
    function taxonomyForm(kind, row = {}) {
        show((row[kind + '_id'] ? 'Edit ' : 'New ') + kind,'<form data-taxonomy-form>' + field('Name',input('name',row.name || '','text','required maxlength="150"')) + field('URL key (automatic when empty)',input('slug',row.slug || '')) + (kind === 'author' ? field('Expertise',input('expertise',row.expertise || '','text','maxlength="300"')) + field('Biography','<textarea name="biography" rows="6" maxlength="5000">' + escape(row.biography) + '</textarea>') + '<input type="hidden" name="image" value="' + escape(row.image || '') + '"><div data-author-image>' + (row.image ? '<img src="/media/' + escape(row.image) + '" alt="Author image preview">' : '') + '</div><div class="blog-studio-tools"><button type="button" data-choose-author-image>Choose author image</button><button type="button" data-clear-author-image>Remove image</button></div><label class="blog-check"><input type="checkbox" name="is_active" value="1" ' + (row.author_id ? Number(row.is_active) ? 'checked' : '' : 'checked') + '> Active profile</label>' : '') + '<button type="submit">Save ' + kind + '</button></form>');
        const form = dialog.querySelector('form');
        form.querySelector('[data-choose-author-image]')?.addEventListener('click',() => mediaPicker(item => { const path = String(item.source).replace(/^\{\{media\s+url\s*=\s*["']?/,'').replace(/["']?\}\}$/,''); form.elements.image.value = path; form.querySelector('[data-author-image]').innerHTML = '<img src="' + escape(item.url) + '" alt="Author image preview">'; }));
        form.querySelector('[data-clear-author-image]')?.addEventListener('click',() => { form.elements.image.value = ''; form.querySelector('[data-author-image]').replaceChildren(); });
        form.addEventListener('submit',async event => { event.preventDefault(); const values = Object.fromEntries(new FormData(form)); const button = form.querySelector('[type=submit]'); button.disabled = true; try { await command('taxonomy',{...values,kind,id:Number(row[kind + '_id'] || 0),fingerprint:row.fingerprint || ''}); dialog.close(); await load(); notice(kind === 'author' ? 'Author profile saved.' : 'Topic saved.'); } catch (error) { notice(error.message,true); } finally { button.disabled = false; } });
    }
    async function mediaPicker(choose) {
        show('Choose an image','<label>Upload image<input type="file" data-media-upload accept="image/jpeg,image/png,image/webp,image/gif"></label><div class="blog-studio-media">Loading images…</div>',mediaDialog);
        const result = await command('media'); let items = result.media || [];
        const draw = () => { mediaDialog.querySelector('.blog-studio-media').innerHTML = items.map((item,index) => '<button type="button" data-media-index="' + index + '"><img src="' + escape(item.url) + '" alt="' + escape(item.name) + '" loading="lazy"><span>' + escape(item.name) + '</span></button>').join(''); };
        draw(); mediaDialog.querySelector('.blog-studio-media').addEventListener('click',event => { const button = event.target.closest('[data-media-index]'); if (button) { choose(items[Number(button.dataset.mediaIndex)]); mediaDialog.close(); } });
        mediaDialog.querySelector('[data-media-upload]').addEventListener('change',async event => { const file = event.target.files[0]; if (!file) return; try { const answer = await command('upload',{},file); choose(answer.image); mediaDialog.close(); } catch (error) { notice(error.message,true); } });
    }
    function seo() {
        panel.innerHTML = '<h2>SEO and sharing review</h2><p>Inspect a saved draft, then edit its metadata in the visual editor.</p><form class="blog-studio-tools"><label>Article<select name="id">' + options(data.articles,'id','title') + '</select></label><button type="submit">Inspect saved draft</button></form><div data-report></div>';
        panel.querySelector('form').addEventListener('submit',async event => {
            event.preventDefault(); const id = Number(new FormData(event.target).get('id')), button = event.target.querySelector('button'); button.disabled = true;
            try { const result = await command('seo',{entity_type:'article',id}), report = result.report; if (active !== 'seo') return; panel.querySelector('[data-report]').innerHTML = '<p>' + report.reading.words + ' words · about ' + report.reading.minutes + ' min read · ' + report.reading.headings + ' section headings</p><div class="blog-studio-previews"><section class="blog-search-card"><h3>Search preview</h3><strong>' + escape(report.preview.title) + '</strong><small>' + escape(report.preview.url) + '</small><p>' + escape(report.preview.description) + '</p></section><section class="blog-social-card"><h3>Sharing preview</h3>' + (report.preview.social_image ? '<img src="/media/' + escape(report.preview.social_image) + '" alt="Sharing image preview">' : '') + '<strong>' + escape(report.preview.social_title) + '</strong><p>' + escape(report.preview.social_description) + '</p></section></div><ul class="blog-seo-issues">' + report.issues.map(issue => '<li class="' + issue.level + '">' + escape(issue.message) + '</li>').join('') + '</ul><a class="blog-studio-button" href="' + escape(publicEditor(id)) + '">Edit this article ↗</a>'; }
            catch (error) { notice(error.message,true); } finally { button.disabled = false; }
        });
    }
    function languages() {
        const stores = data.stores.filter(store => !store.default);
        panel.innerHTML = '<h2>Store-view translations</h2><p>Translate articles and categories for configured Magento store views. Original content remains the fallback when no translation is published.</p>' + (stores.length ? '<form data-create-language class="blog-studio-tools">' + field('Content type','<select name="kind"><option value="post">Article</option><option value="category">Category</option></select>') + field('Content','<select name="id" data-language-content>' + options(data.articles,'id','title') + '</select>') + field('Store view','<select name="store_id">' + options(stores,'id','name') + '</select>') + '<button type="submit">Create or open translation</button></form>' : '<p class="blog-studio-info">Configure another Magento store view to add a language version.</p>') + table(['Content','Type','Store view','Status','Open'],data.translations.map(row => '<tr><td>' + escape(row.title) + '</td><td>' + escape(row.entity_type === 'post' ? 'Article' : 'Category') + '</td><td>' + escape(data.stores.find(store => store.id === row.store_id)?.name || 'Store view') + '</td><td>' + (row.published ? 'Published' : 'Draft') + '</td><td>' + (row.url ? '<a href="' + escape(row.url) + '">Visual editor ↗</a>' : '<button type="button" data-category-language="' + row.id + '">Edit translation</button>') + '</td></tr>'));
        const form = panel.querySelector('[data-create-language]');
        form?.elements.kind.addEventListener('change',() => { form.elements.id.innerHTML = form.elements.kind.value === 'post' ? options(data.articles,'id','title') : options(data.categories,'category_id','title'); });
        form?.addEventListener('submit',async event => { event.preventDefault(); const values = Object.fromEntries(new FormData(form)); try { const result = await command('create_translation',{kind:values.kind,id:Number(values.id),store_id:Number(values.store_id)}); if (result.url) location.href = result.url; else { await load(); await categoryLanguage(result.id); } } catch (error) { notice(error.message,true); } });
        panel.querySelectorAll('[data-category-language]').forEach(button => button.addEventListener('click',() => categoryLanguage(Number(button.dataset.categoryLanguage)).catch(error => notice(error.message,true))));
    }
    async function categoryLanguage(id) {
        let result = await command('category_translation',{id}), state = result.state;
        show('Category translation','<form>' + ['title','slug','description','meta_title','meta_description'].map(name => field({title:'Title',slug:'URL key',description:'Description',meta_title:'SEO title',meta_description:'SEO description'}[name],name.includes('description') ? '<textarea name="' + name + '" rows="4">' + escape(state.payload[name]) + '</textarea>' : input(name,state.payload[name] || ''))).join('') + '<button type="submit" name="action" value="draft">Save draft</button>' + (config.canPublish ? '<button type="submit" name="action" value="publish">Save and publish</button>' : '') + '<p data-language-status role="status"></p></form>');
        const form = dialog.querySelector('form');
        form.addEventListener('submit',async event => { event.preventDefault(); const publish = event.submitter?.value === 'publish'; if (publish && !confirm('Publish this category translation?')) return; const values = Object.fromEntries(new FormData(form)); const buttons = form.querySelectorAll('button'); buttons.forEach(button => { button.disabled = true; }); try { result = await command('save_category_translation',{...values,id,version:state.version,live_hash:state.live_hash,publish}); state = result.state; form.querySelector('[data-language-status]').textContent = publish ? 'Translation published.' : 'Translation draft saved.'; } catch (error) { notice(error.message,true); } finally { buttons.forEach(button => { button.disabled = false; }); } });
        dialog.addEventListener('close',() => load().catch(error => notice(error.message,true)),{once:true});
    }
    async function feedback() {
        const result = await command('moderation'); if (active !== 'feedback') return;
        panel.innerHTML = '<h2>Reader feedback</h2><p>Review comments before they appear publicly. Showing the latest 200 submissions.</p>' + table(['Article','Reader','Comment','Status','Moderation'],result.feedback.map(row => '<tr><td><a href="' + escape(publicEditor(row.post_id)) + '">Open article ↗</a></td><td>' + escape(row.name) + '<small>' + escape(row.created_at) + ' UTC</small></td><td class="blog-comment-body">' + escape(row.body) + '</td><td>' + escape(row.status) + '</td><td>' + ['approved','rejected','pending'].filter(status => status !== row.status).map(status => '<button type="button" data-moderate="' + row.feedback_id + '" data-previous="' + row.status + '" data-status="' + status + '">' + {approved:'Approve',rejected:'Reject',pending:'Hold'}[status] + '</button>').join('') + '</td></tr>'));
        panel.querySelectorAll('[data-moderate]').forEach(button => button.addEventListener('click',async () => { button.disabled = true; try { await command('moderate',{id:Number(button.dataset.moderate),status:button.dataset.status,previous:button.dataset.previous}); await feedback(); notice('Comment moderation saved.'); } catch (error) { notice(error.message,true); button.disabled = false; } }));
    }
    async function analytics() {
        const result = await command('analytics'); if (active !== 'analytics') return;
        const rows = new Map(), totals = {view:0,product_click:0,service_click:0,enquiry:0};
        result.metrics.forEach(item => { if (!rows.has(Number(item.post_id))) rows.set(Number(item.post_id),{id:Number(item.post_id),title:item.title || 'Archived article'}); rows.get(Number(item.post_id))[item.event] = Number(item.total); if (item.event in totals) totals[item.event] += Number(item.total); });
        const maximum = Math.max(1,...[...rows.values()].map(row => row.view || 0));
        panel.innerHTML = '<h2>Article performance</h2><p>Approximate aggregate reads and clicks. Enquiries count completed service submissions attributed to an article.</p><div class="blog-studio-metrics">' + Object.entries(totals).map(([name,value]) => '<article><strong>' + value + '</strong><span>' + {view:'Reads',product_click:'Product clicks',service_click:'Service clicks',enquiry:'Service enquiries'}[name] + '</span></article>').join('') + '</div>' + table(['Article','Reads','Product clicks','Service clicks','Enquiries','Helpful'],[...rows.values()].sort((a,b) => (b.view || 0) - (a.view || 0)).map(row => '<tr><td><a href="' + escape(publicEditor(row.id)) + '">' + escape(row.title) + '</a><span class="blog-metric-bar" style="width:' + Math.round((row.view || 0) / maximum * 100) + '%"></span></td><td>' + (row.view || 0) + '</td><td>' + (row.product_click || 0) + '</td><td>' + (row.service_click || 0) + '</td><td>' + (row.enquiry || 0) + '</td><td>' + (row.helpful_yes || 0) + ' yes / ' + (row.helpful_no || 0) + ' no</td></tr>'));
    }
    async function subscriptions() {
        const result = await command('delivery'); if (active !== 'subscriptions') return;
        const settings = result.settings;
        panel.innerHTML = '<h2>Subscriptions and reader settings</h2><div class="blog-studio-metrics">' + result.subscribers.map(row => '<article><strong>' + row.count + '</strong><span>' + escape(row.status) + '</span></article>').join('') + '</div><form data-reader-settings class="blog-studio-settings">' + [['comments_enabled','Enable moderated comments'],['subscriptions_enabled','Enable subscriptions and digests'],['delivery_enabled','Enable email delivery']].map(([name,label]) => '<label class="blog-check"><input type="checkbox" name="' + name + '" value="1" ' + (settings[name] ? 'checked' : '') + '> ' + label + '</label>').join('') + field('Digest frequency','<select name="digest"><option value="weekly" ' + (settings.digest === 'weekly' ? 'selected' : '') + '>Weekly</option><option value="daily" ' + (settings.digest === 'daily' ? 'selected' : '') + '>Daily</option></select>') + field('Analytics and rejected-feedback retention (days)',input('retention_days',settings.retention_days,'number','min="30" max="730" required')) + '<p>Email delivery uses the store’s configured transactional mail service. Development messages are captured by Mailpit.</p><button type="submit">Save reader settings</button></form><h3>Email delivery queue</h3>' + table(['Type','Status','Attempts','Next attempt (UTC)','Action'],result.mail.map(row => '<tr><td>' + escape(row.kind) + '</td><td>' + escape(row.state) + '</td><td>' + row.attempts + '</td><td>' + escape(row.next_attempt_at) + '</td><td>' + (row.state === 'failed' ? '<button type="button" data-retry-mail="' + row.mail_id + '">Retry delivery</button>' : '') + '</td></tr>'));
        panel.querySelector('form').addEventListener('submit',async event => { event.preventDefault(); const values = Object.fromEntries(new FormData(event.target)); try { await command('settings',values); await subscriptions(); notice('Reader settings saved.'); } catch (error) { notice(error.message,true); } });
        panel.querySelectorAll('[data-retry-mail]').forEach(button => button.addEventListener('click',async () => { try { await command('retry_mail',{id:Number(button.dataset.retryMail)}); await subscriptions(); notice('Delivery queued for retry.'); } catch (error) { notice(error.message,true); } }));
    }
    function transfer() {
        panel.innerHTML = '<h2>Article import and export</h2><div class="blog-studio-previews"><section><h3>Export content</h3><p>' + selected.size + ' articles selected. Select up to 50 articles in the Articles tab.</p><button type="button" data-export-json>Download articles and media</button><button type="button" data-export-csv>Download selected article summary (CSV)</button></section><section><h3>Import drafts</h3><p>Import a Shi3ld blog JSON package, up to 20 MB. Articles and linked designs are created as drafts. Existing content is preserved.</p>' + (config.canPublish && config.canDesign ? '<form data-import><label>Blog package<input type="file" name="package" accept="application/json,.json" required></label><button type="submit">Import as drafts</button></form>' : '<p>Import requires design and publication permission.</p>') + '</section></div>';
        panel.querySelector('[data-export-json]').addEventListener('click',() => exportSelected().catch(error => notice(error.message,true)));
        panel.querySelector('[data-export-csv]').addEventListener('click',() => { if (!selected.size) { notice('Select articles first.',true); return; } const cell = value => { const text = String(value ?? ''); return '"' + (/^[\s]*[=+@-]/.test(text) ? "'" : '') + text.replace(/"/g,'""') + '"'; }; const rows = [['id','title','slug','published','assigned_to','deadline','review_date'],...[...selected].map(id => { const row = known.get(id); return [id,row.title,row.slug,row.published ? 'yes' : 'no',row.editorial.assigned_to,row.editorial.due_at,row.editorial.review_at]; })]; download('\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n'),'shi3ld-articles.csv','text/csv;charset=utf-8'); });
        panel.querySelector('[data-import]')?.addEventListener('submit',async event => { event.preventDefault(); const file = event.target.elements.package.files[0], button = event.target.querySelector('button'); if (!file) return; if (file.size > 20000000) { notice('Choose a package up to 20 MB.',true); return; } button.disabled = true; try { const result = await command('import',{},file); await load(); notice(result.message + (result.warnings?.length ? '\n' + result.warnings.join('\n') : '')); } catch (error) { notice(error.message,true); } finally { button.disabled = false; } });
    }
    function download(contents, name, type = 'application/json') { const url = URL.createObjectURL(new Blob([contents],{type})), anchor = document.createElement('a'); anchor.href = url; anchor.download = name; anchor.click(); setTimeout(() => URL.revokeObjectURL(url),1000); }
    async function exportSelected() { if (!selected.size) throw new Error('Select articles in the Articles tab first.'); const result = await command('export',{ids:[...selected]}); download(JSON.stringify(result.package,null,2),'shi3ld-blog.json'); notice('Blog package downloaded.'); }
    load().catch(error => { panel.innerHTML = '<p>Blog management could not be loaded.</p>'; notice(error.message,true); });
}());
