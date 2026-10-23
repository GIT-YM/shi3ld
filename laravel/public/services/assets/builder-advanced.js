(function () {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
    const random = () => [...crypto.getRandomValues(new Uint8Array(8))].map(number => number.toString(16).padStart(2,'0')).join('');
    const readJson = (element, name, fallback = {}) => { try { return JSON.parse(element.getAttribute(name) || JSON.stringify(fallback)); } catch (_) { return fallback; } };
    const defaults = () => ({css:'',classes:'',theme:{},conditions:{include:'',exclude:'',audience:'all',start:'',end:''},asset:{}});
    const field = (label, control) => `<label class="editor-field">${escape(label)}${control}</label>`;
    const input = (name, value = '', type = 'text') => `<input data-advanced="${escape(name)}" type="${type}" value="${escape(value)}">`;
    const select = (name, values, chosen = '') => `<select data-advanced="${escape(name)}">${Object.entries(values).map(([value,label]) => `<option value="${escape(value)}" ${String(chosen) === value ? 'selected' : ''}>${escape(label)}</option>`).join('')}</select>`;
    window.Shi3ldBuilderAdvanced = {
        mount(api) {
            const {config,host} = api;
            const enabled = Boolean(config.advanced);
            if (!enabled) return null;
            const designer = config.canDesign !== false;
            let applying = false, snapshots = [], future = [], snapshotTimer, multi = new Set(), clipboard = null, device = 'desktop';
            let assets = [], lastSnapshot = null, dragging = null, resizeHandle = null;
            const $ = selector => host.querySelector(selector);
            function settings() { const current = api.getPayload(); return {...defaults(),...(current.builder || {})}; }
            function updateSettings(next) { api.updatePayload({builder:next}); api.changed(); }
            function nodeId(element) { if (!element.dataset.builderNode) element.dataset.builderNode = random(); return element.dataset.builderNode; }
            function targets() { return multi.size ? [...multi].map(key => api.source()?.querySelector('[data-cms-node="' + key + '"]')).filter(Boolean) : [api.selected()].filter(Boolean); }
            function notice(message, error = false) { api.notice(message,error); }
            function changed() {
                if (applying) return;
                window.clearTimeout(snapshotTimer);
                snapshotTimer = window.setTimeout(() => {
                    const current = JSON.stringify(api.getPayload());
                    if (current === lastSnapshot) return;
                    if (lastSnapshot) snapshots.push(lastSnapshot);
                    snapshots = snapshots.slice(-60); future = []; lastSnapshot = current; buttons(); structure();
                }, 300);
            }
            function buttons() { $('#advanced-undo').disabled = !snapshots.length; $('#advanced-redo').disabled = !future.length; }
            function history(direction) {
                window.clearTimeout(snapshotTimer);
                const now = JSON.stringify(api.getPayload());
                if (lastSnapshot !== now && lastSnapshot) { snapshots.push(lastSnapshot); lastSnapshot = now; future = []; }
                const from = direction === 'undo' ? snapshots : future, to = direction === 'undo' ? future : snapshots;
                if (!from.length) return;
                to.push(lastSnapshot); lastSnapshot = from.pop(); applying = true;
                api.setPayload(JSON.parse(lastSnapshot)); applying = false; buttons(); structure(); if(!panel.hidden)pagePanel();
            }
            const extra = document.createElement('div'); extra.className = 'advanced-top-tools';
            extra.innerHTML = '<button id="advanced-undo" title="Undo (Ctrl/⌘ Z)" disabled>↶</button><button id="advanced-redo" title="Redo (Ctrl/⌘ Shift Z)" disabled>↷</button><button id="advanced-library">Library</button>';
            $('.editor-top').insertBefore(extra,$('.editor-actions'));
            const tab = document.createElement('button'); tab.dataset.tab = 'advanced'; tab.textContent = 'Design'; $('.editor-tabs').append(tab);
            const panel = document.createElement('section'); panel.id = 'editor-advanced'; panel.className = 'editor-tab'; panel.hidden = true; $('.editor-sidebar').append(panel);
            tab.addEventListener('click', () => { host.querySelectorAll('[data-tab]').forEach(button => button.classList.toggle('active',button === tab)); host.querySelectorAll('.editor-tab').forEach(item => { item.hidden = item !== panel; }); pagePanel(); });
            const tree = document.createElement('div'); tree.id = 'advanced-tree'; $('#editor-section-list').before(tree); $('#editor-section-list').hidden = true;
            const search = document.createElement('input'); search.className = 'advanced-tree-search'; search.type = 'search'; search.placeholder = 'Find an element…'; search.setAttribute('aria-label','Find an element'); tree.before(search);
            search.addEventListener('input',structure);
            $('#advanced-undo').addEventListener('click',() => history('undo')); $('#advanced-redo').addEventListener('click',() => history('redo'));
            $('#advanced-library').addEventListener('click',openLibrary);
            const modal = document.createElement('dialog'); modal.id = 'advanced-modal'; modal.className = 'advanced-modal'; host.append(modal);
            function show(title, html) { modal.innerHTML = `<div class="dialog-heading"><h2>${escape(title)}</h2><button data-modal-close aria-label="Close">×</button></div>${html}`; modal.querySelector('[data-modal-close]').addEventListener('click',() => modal.close()); if (!modal.open) modal.showModal(); }
            function styleFields(element) {
                const all = readJson(element,'data-builder-styles'), current = all[device] || {};
                const groups = [
                    ['Typography',{'font-size':'Font size','font-family':'Font family','font-weight':'Font weight','line-height':'Line height','letter-spacing':'Letter spacing','color':'Text colour','text-align':'Text alignment'}],
                    ['Layout',{'display':'Display (block/flex/grid/none)','grid-template-columns':'Grid columns, e.g. repeat(3,1fr)','gap':'Gap','flex-direction':'Flex direction','flex-wrap':'Flex wrap','justify-content':'Horizontal alignment','align-items':'Vertical alignment','order':'Element order','width':'Width','max-width':'Maximum width','min-height':'Minimum height'}],
                    ['Spacing',{'margin':'Margin','padding':'Padding','margin-top':'Top margin','margin-bottom':'Bottom margin','padding-top':'Top padding','padding-bottom':'Bottom padding'}],
                    ['Borders and images',{'background-color':'Background colour','background-image':'Gradient (linear-gradient(...))','border':'Border','border-radius':'Rounded corners','box-shadow':'Shadow','aspect-ratio':'Image ratio (e.g. 4/3)','object-fit':'Image fit (cover/contain)','object-position':'Image focal point (e.g. 50% 30%)'}],
                    ['Position and effects',{'position':'Position (static/relative/sticky)','top':'Top offset','z-index':'Layer order','opacity':'Opacity','transform':'Transform','transition':'Transition'}],
                ];
                return `<div class="advanced-device-select">${select('device',{desktop:'Desktop',tablet:'Tablet ≤1024px',mobile:'Mobile ≤640px',hover:'Hover',focus:'Keyboard focus'},device)}</div><p class="editor-hint">Use CSS units, such as 24px, 1.5rem or 50%. Empty values inherit the theme. ${multi.size ? multi.size + ' selected elements.' : ''}</p>${groups.map(([label,properties]) => `<details class="advanced-group"><summary>${label}</summary>${Object.entries(properties).map(([property,label]) => field(label,`<input data-style-property="${property}" value="${escape(current[property] || '')}" placeholder="Theme default">`)).join('')}</details>`).join('')}`;
            }
            function conditionsFields(value) {
                value = {...defaults().conditions,...value};
                return field('Include paths (comma separated)',input('include',value.include)) + field('Exclude paths',input('exclude',value.exclude)) + field('Audience',select('audience',{all:'Everyone',guest:'Guests',customer:'Signed-in customers',staff:'Staff preview'},value.audience)) + field('Starts (UTC)',input('start',value.start,'datetime-local')) + field('Ends (UTC)',input('end',value.end,'datetime-local'));
            }
            function conditionValue(root) { const result = {}; ['include','exclude','audience','start','end'].forEach(name => { result[name] = root.querySelector('[data-advanced="' + name + '"]').value; }); return result; }
            function attachCssEditor(root, value, onChange) {
                root.innerHTML = '<div class="advanced-code"><pre aria-hidden="true"></pre><textarea spellcheck="false" aria-label="Custom CSS" placeholder="selector { color: #173d32; }"></textarea></div><p class="editor-hint">Use selector for this scope. @media and @supports are supported. Ctrl/⌘ Space suggests CSS properties.</p>';
                const area = root.querySelector('textarea'), pre = root.querySelector('pre'); area.value = value;
                function highlight() { pre.innerHTML = escape(area.value).replace(/([\w-]+)(?=\s*:)/g,'<span class="code-property">$1</span>').replace(/(#[a-fA-F0-9]{3,8}\b|\b\d+(?:px|rem|em|vh|vw|%)\b)/g,'<span class="code-value">$1</span>') + '\n'; }
                area.addEventListener('input',() => { highlight(); onChange(area.value); });
                area.addEventListener('scroll',() => { pre.scrollTop = area.scrollTop; pre.scrollLeft = area.scrollLeft; });
                area.addEventListener('keydown',event => { event.stopPropagation(); if (event.key === 'Tab') { event.preventDefault(); area.setRangeText('  ',area.selectionStart,area.selectionEnd,'end'); area.dispatchEvent(new Event('input')); } if ((event.ctrlKey || event.metaKey) && event.code === 'Space') { event.preventDefault(); const word = area.value.slice(0,area.selectionStart).match(/[a-z-]*$/)[0]; const property = config.advanced.properties.find(name => name.startsWith(word)); if (property) { area.setRangeText(property + ': ',area.selectionStart-word.length,area.selectionEnd,'end'); area.dispatchEvent(new Event('input')); } } }); highlight();
            }
            function resize(element) {
                resizeHandle?.remove(); resizeHandle=null;
                if(!designer||!element||!api.frame())return;
                const doc=api.frame(),live=doc.querySelector('[data-cms-node="'+element.dataset.cmsNode+'"]');if(!live)return;
                const rect=live.getBoundingClientRect();if(rect.width<30||rect.height<20)return;
                const handle=doc.createElement('button');handle.type='button';handle.title='Drag to resize width';handle.setAttribute('aria-label','Drag to resize width');handle.textContent='↔';handle.style.cssText='position:fixed;left:'+(rect.right-14)+'px;top:'+(rect.top+rect.height/2-16)+'px;width:28px;height:32px;z-index:2147483647;background:#173d32;color:white;border:2px solid white;border-radius:6px;cursor:ew-resize;padding:0';doc.body.append(handle);resizeHandle=handle;
                handle.addEventListener('pointerdown',event=>{event.preventDefault();event.stopPropagation();handle.setPointerCapture(event.pointerId);const start=event.clientX,width=rect.width,parent=live.parentElement.getBoundingClientRect().width;const move=event=>{const percent=Math.min(100,Math.max(10,(width+event.clientX-start)/parent*100));nodeId(element);const styles=readJson(element,'data-builder-styles');styles[device]||={};styles[device].width=percent.toFixed(1)+'%';element.dataset.builderStyles=JSON.stringify(styles);live.style.setProperty('width',percent.toFixed(1)+'%','important');handle.style.left=(live.getBoundingClientRect().right-14)+'px';};const end=()=>{handle.removeEventListener('pointermove',move);api.changed();api.render();};handle.addEventListener('pointermove',move);handle.addEventListener('pointerup',end,{once:true});handle.addEventListener('pointercancel',end,{once:true});});
            }
            function inspector(target, element, readOnly) {
                if (!element || readOnly) {resizeHandle?.remove();return;} resize(element);
                const section = document.createElement('div'); section.className = 'advanced-inspector';
                const binding = element.getAttribute('data-builder-field') || '';
                const locked = element.getAttribute('data-builder-lock') === '1';
                section.innerHTML = '<h3>Advanced element settings</h3>' + field('Element name',input('label',element.dataset.builderLabel || '')) + field('CSS classes',input('classes',element.getAttribute('class') || '')) + (designer ? `<label class="editor-field advanced-checkbox"><input data-advanced="locked" type="checkbox" ${locked?'checked':''}> Lock content and layout for content editors</label>${styleFields(element)}<div class="advanced-button-row"><button data-action="copy-style">Copy style</button><button data-action="paste-style">Paste style</button><button data-action="reset-style">Reset styles</button><button data-action="save-section">Save as reusable section</button><button data-action="duplicate">Duplicate element</button><button data-action="remove">Remove element</button><button data-action="move-up">Move up</button><button data-action="move-down">Move down</button><button data-action="nest">Add nested container</button></div><details class="advanced-group"><summary>Element CSS</summary><div class="advanced-element-css"></div></details><details class="advanced-group advanced-conditions"><summary>Display conditions</summary>${conditionsFields(readJson(element,'data-builder-conditions'))}</details>` : '<p class="editor-hint">Your role can edit content; design controls are managed by a designer.</p>') + field('Dynamic content',select('binding',{'':'Static content',...Object.fromEntries(config.advanced.fields.map(name=>[name,name]))},binding)) + field('Entrance animation',select('animation',{none:'None',fade:'Fade in',rise:'Rise',slide:'Slide'},element.dataset.builderAnimation || 'none')) + '<button data-action="background-image">Choose background image</button><button data-action="clear-background">Remove background image</button>';
                if (element.dataset.builderWidget === 'video') section.insertAdjacentHTML('beforeend',field('YouTube or Vimeo URL',input('video',element.dataset.builderVideo || '')));
                if (element.tagName === 'NAV' || element.dataset.builderWidget === 'navigation') section.insertAdjacentHTML('beforeend','<button data-action="add-link">Add navigation link</button>');
                if (element.hasAttribute('data-builder-ref')) section.insertAdjacentHTML('beforeend','<p class="editor-hint">This is a linked block. Edit its original in the Library to update every instance.</p><button data-action="edit-linked">Open original design ↗</button>');
                target.append(section);
                if (!designer) { target.querySelectorAll('#selected-align,#selected-color,#selected-background,#selected-padding,#selected-width,#selected-tag').forEach(control => { control.closest('label').hidden = true; }); section.querySelectorAll('[data-advanced], [data-action]').forEach(control=>control.disabled=true);target.querySelectorAll('[data-format],[data-link-move]').forEach(control=>control.disabled=true); return; }
                section.querySelectorAll('[data-style-property]').forEach(control => control.addEventListener('change',() => { targets().forEach(node => { nodeId(node); const values = readJson(node,'data-builder-styles'); values[device] ||= {}; if (control.value.trim()) values[device][control.dataset.styleProperty] = control.value.trim(); else delete values[device][control.dataset.styleProperty]; node.setAttribute('data-builder-styles',JSON.stringify(values)); }); api.changed(); api.render(); }));
                section.querySelector('[data-advanced="device"]').addEventListener('change',event => { device = event.target.value; api.inspect(); });
                section.querySelector('[data-advanced="label"]').addEventListener('change',event => { element.dataset.builderLabel = event.target.value.slice(0,100); api.changed(); structure(); });
                section.querySelector('[data-advanced="classes"]').addEventListener('change',event => { element.setAttribute('class',event.target.value); api.changed(); api.render(); });
                section.querySelector('[data-advanced="locked"]').addEventListener('change',event => { element.dataset.builderLock = event.target.checked ? '1' : '0'; api.changed(); structure(); });
                section.querySelector('[data-advanced="binding"]').addEventListener('change',event => { if (event.target.value) element.dataset.builderField = event.target.value; else element.removeAttribute('data-builder-field'); api.changed(); api.render(); });
                section.querySelector('[data-advanced="animation"]').addEventListener('change',event => { element.dataset.builderAnimation = event.target.value; api.changed(); api.render(); });
                section.querySelector('[data-advanced="video"]')?.addEventListener('change',event => { if (event.target.value.trim()) element.dataset.builderVideo = event.target.value.trim(); else element.removeAttribute('data-builder-video'); api.changed(); api.render(); });
                section.querySelector('.advanced-conditions').addEventListener('change',() => { element.dataset.builderConditions = JSON.stringify(conditionValue(section.querySelector('.advanced-conditions'))); api.changed(); api.render(); });
                attachCssEditor(section.querySelector('.advanced-element-css'),element.dataset.builderCss || '',value => { nodeId(element); element.dataset.builderCss = value; api.changed(); api.render(); });
                section.addEventListener('click',async event => {
                    const action = event.target.closest('[data-action]')?.dataset.action; if (!action) return; event.preventDefault();
                    if(action==='duplicate'){const clone=element.cloneNode(true);cloneIds(clone);element.after(clone);api.refresh();}
                    if(action==='remove'&&confirm('Remove this element from the working draft?')){element.remove();api.refresh();}
                    if(action==='move-up'&&element.previousElementSibling){element.previousElementSibling.before(element);api.refresh();}
                    if(action==='move-down'&&element.nextElementSibling){element.nextElementSibling.after(element);api.refresh();}
                    if(action==='nest'){if(!['DIV','SECTION','ARTICLE','HEADER','FOOTER','NAV'].includes(element.tagName)){notice('Choose a container in the navigator first.');return;}const child=document.createElement('div');child.dataset.builderNode=random();child.dataset.builderStyles=JSON.stringify({desktop:{display:'flex',gap:'24px','flex-wrap':'wrap'}});child.innerHTML='<div><h3>Your nested content</h3><p>Edit this text.</p></div>';element.append(child);api.refresh();}
                    if (action === 'copy-style') { clipboard = {style:element.getAttribute('style') || '',responsive:element.dataset.builderStyles || '{}',css:element.dataset.builderCss || ''}; notice('Styles copied. Select another element and choose Paste style.'); }
                    if (action === 'paste-style' && clipboard) { targets().forEach(node => { nodeId(node); node.setAttribute('style',clipboard.style); node.dataset.builderStyles = clipboard.responsive; node.dataset.builderCss = clipboard.css; }); api.changed(); api.render(); api.inspect(); }
                    if (action === 'reset-style' && confirm('Reset this element to the theme styles?')) { targets().forEach(node => { ['style','data-builder-styles','data-builder-css','data-builder-background','data-builder-animation'].forEach(name=>node.removeAttribute(name)); }); api.changed(); api.render(); api.inspect(); }
                    if (action === 'background-image') api.chooseMedia(image => { nodeId(element); element.dataset.builderBackground = image.url; api.changed(); api.render(); });
                    if (action === 'clear-background') { element.removeAttribute('data-builder-background'); api.changed(); api.render(); }
                    if (action === 'save-section') saveAsset(element.outerHTML,'section');
                    if (action === 'add-link') { const name = prompt('Link text'); if (!name) return; const href = prompt('Website path or HTTPS URL','/services'); if (!href) return; const link = document.createElement('a'); link.href=href; link.textContent=name; element.append(link); api.refresh(); }
                    if (action === 'edit-linked') { await loadAssets(); const original = assets.find(item=>item.key===element.dataset.builderRef); if (original) window.open(original.url,'_blank','noopener'); }
                });
            }
            function structure() {
                if (!api.source()) return;
                const query = search.value.toLowerCase();
                function nodes(parent, depth = 0) {
                    if (depth > 12) return '';
                    return [...parent.children].filter(element=>!['STYLE','SCRIPT'].includes(element.tagName)).map(element => {
                        const label = element.dataset.builderLabel || element.getAttribute('data-content-type') || element.tagName.toLowerCase();
                        const text = (element.textContent || '').trim().replace(/\s+/g,' ').slice(0,35);
                        const child = nodes(element,depth+1);
                        if (query && !(label + ' ' + text).toLowerCase().includes(query) && !child) return '';
                        const key = element.dataset.cmsNode;
                        return `<details class="advanced-tree-item" ${depth===0 || query ? 'open' : ''} data-tree-key="${escape(key)}"><summary draggable="${designer}" data-tree-select="${escape(key)}"><span>${element.dataset.builderLock==='1'?'🔒 ':''}${escape(label)}</span><small>${escape(text)}</small></summary>${child}</details>`;
                    }).join('');
                }
                tree.innerHTML = nodes(api.source());
            }
            tree.addEventListener('click',event => { const summary=event.target.closest('[data-tree-select]'); if (!summary) return; const node=api.source().querySelector('[data-cms-node="'+summary.dataset.treeSelect+'"]'); if (event.shiftKey) toggle(node); else { multi.clear(); api.select(node); } });
            tree.addEventListener('dragstart',event=>{ if(!designer)return; dragging=event.target.closest('[data-tree-key]')?.dataset.treeKey; event.dataTransfer.setData('text/plain',dragging||''); });
            tree.addEventListener('dragover',event=>{if(designer)event.preventDefault();});
            tree.addEventListener('drop',event=>{event.preventDefault();const target=event.target.closest('[data-tree-key]')?.dataset.treeKey;if(!designer||!dragging||!target||target===dragging)return;const node=api.source().querySelector('[data-cms-node="'+dragging+'"]'),before=api.source().querySelector('[data-cms-node="'+target+'"]');if(node.contains(before)){notice('An element cannot be moved inside itself.',true);return;}before.before(node);api.refresh();});
            function toggle(node) { if (!node) return; const key=node.dataset.cmsNode; if(multi.has(key))multi.delete(key);else multi.add(key); const active=multi.has(key)||!multi.size?node:api.source().querySelector('[data-cms-node="'+[...multi].at(-1)+'"]');api.select(active); notice(multi.size+' elements selected. Appearance changes apply to all selected elements.'); }
            function cloneIds(node) { const ids={}; [node,...node.querySelectorAll('*')].forEach(item=>{item.removeAttribute('data-cms-node');if(item.hasAttribute('data-builder-node')||item.hasAttribute('data-builder-styles')||item.hasAttribute('data-builder-css'))item.dataset.builderNode=random();if(item.id){const old=item.id;item.id=old+'-'+random().slice(0,5);ids[old]=item.id;}});[node,...node.querySelectorAll('*')].forEach(item=>['aria-controls','aria-labelledby','for'].forEach(name=>{if(ids[item.getAttribute(name)])item.setAttribute(name,ids[item.getAttribute(name)]);})); }
            function insert(html) { const holder=document.createElement('div');holder.innerHTML=html;[...holder.children].forEach(node=>{cloneIds(node);api.source().append(node);});api.refresh(); }
            function pagePanel() {
                const values=settings(), theme=values.theme||{}, asset=values.asset||{};
                panel.innerHTML='<h2>Design & publishing</h2>'+(designer ? field('Page CSS classes',input('page-classes',values.classes))+'<details class="advanced-group" open><summary>'+(config.assetKind==='global'?'Global CSS':'Page CSS')+'</summary><div id="advanced-page-css"></div></details>' : '<p>Your role can edit content and request publication.</p>')+'<div class="advanced-button-row"><button data-publish-tool="review">Request review</button><button data-publish-tool="compare">Compare published version</button>'+(config.canPublish?'<button data-publish-tool="schedule">Schedule publication</button>':'')+'<button data-publish-tool="cancel">Cancel scheduled publication</button></div><div id="advanced-workflow-status"></div><details class="advanced-group"><summary>Accessibility & page checks</summary><button id="advanced-audit">Review this page</button><div id="advanced-audit-results"></div></details>'+(designer?'<div class="advanced-button-row"><button id="advanced-save-page">Save page as template</button><button id="advanced-export">Export design & media</button><label class="advanced-upload">Import design<input id="advanced-import" type="file" accept="application/json,.json" hidden></label></div>':'');
                if (designer) {
                    attachCssEditor($('#advanced-page-css'),values.css||'',value=>{const next=settings();next.css=value;updateSettings(next);api.render();});
                    $('[data-advanced="page-classes"]').addEventListener('change',event=>{const next=settings();next.classes=event.target.value;updateSettings(next);api.render();});
                    $('#advanced-save-page').addEventListener('click',()=>saveAsset(api.getPayload().html,'template'));
                    $('#advanced-export').addEventListener('click',async()=>{try{await api.save();const data=await api.command('export_design');download(data.package,'shi3ld-design.json');}catch(error){notice(error.message,true);}});
                    $('#advanced-import').addEventListener('change',async event=>{const file=event.target.files[0];if(!file)return;try{const data=await api.command('import_design',{package:file});api.setPayload({...api.getPayload(),...data.payload});notice('Design imported into your working draft. Review the Library for imported linked designs before publishing.');}catch(error){notice(error.message,true);}finally{event.target.value='';}});
                }
                if (designer && config.assetKind==='global') {
                    const global=document.createElement('details');global.className='advanced-group';global.open=true;global.innerHTML='<summary>Shared brand settings</summary>'+Object.entries({primary:'Primary colour',secondary:'Secondary colour',accent:'Accent colour',text:'Text colour',background:'Background colour',font:'Body font family',heading_font:'Heading font family',radius:'Button corner radius',content_width:'Content width',spacing:'Default spacing',button_padding:'Button padding'}).map(([name,label])=>field(label,`<input data-theme="${name}" value="${escape(theme[name]||'')}" placeholder="Theme default">`)).join('');panel.prepend(global);global.addEventListener('change',event=>{if(!event.target.dataset.theme)return;const next=settings();next.theme={...(next.theme||{}),[event.target.dataset.theme]:event.target.value};updateSettings(next);api.render();});
                }
                if (designer && config.assetKind) {
                    const options=document.createElement('details');options.className='advanced-group';options.open=true;options.innerHTML='<summary>Design type: '+escape(config.assetKind)+'</summary>'+field('Applies to',select('asset-target',Object.fromEntries(config.advanced.targets.map(name=>[name,name==='none'?'Library only':name])),asset.target||(config.assetKind==='template'?'none':'all')))+field('Priority (higher wins)',input('asset-priority',asset.priority??10,'number'))+'<div class="advanced-asset-conditions">'+conditionsFields(values.conditions)+'</div>';
                    if(config.assetKind==='popup')options.insertAdjacentHTML('beforeend',field('Trigger',select('asset-trigger',{delay:'After delay',scroll:'After scrolling',click:'Button trigger',banner:'Announcement banner'},asset.trigger||'delay'))+field('Delay (seconds)',input('asset-delay',asset.delay??5,'number'))+field('Scroll percentage',input('asset-scroll',asset.scroll??60,'number'))+field('Frequency',select('asset-frequency',{session:'Once per session',day:'Once per day',always:'Every visit'},asset.frequency||'session'))+'<p class="editor-hint">For a button trigger, use this link destination: <code>#popup-'+escape(config.assetKey)+'</code></p>');
                    if(config.assetKind==='product')options.insertAdjacentHTML('beforeend',field('Product arrangement',select('asset-product_layout',{'gallery-left':'Gallery left','gallery-right':'Gallery right','gallery-top':'Gallery above information'},asset.product_layout||'gallery-left')));
                    if(config.assetKind==='form')options.insertAdjacentHTML('beforeend','<p class="editor-hint">Name, email, postcode, requirements and privacy acknowledgement are always collected. Additional fields appear below them.</p>'+field('Service type',select('form-service_type',{domestic:'Domestic',commercial:'Commercial',care_home:'Care home'},asset.form?.service_type||'domestic'))+field('Button label',input('form-button',asset.form?.button||'Send enquiry'))+field('Success message',input('form-success',asset.form?.success||'Thank you. Your enquiry has been received.'))+'<div id="advanced-form-fields"></div><button id="advanced-add-field">Add form field</button>');
                    options.addEventListener('change',event=>{const name=event.target.dataset.advanced;if(!name)return;const next=settings();next.asset={...asset,...next.asset};if(name.startsWith('asset-'))next.asset[name.slice(6)]=['priority','delay','scroll'].includes(name.slice(6))?Number(event.target.value):event.target.value;else if(name.startsWith('form-')){next.asset.form={...(next.asset.form||{}),[name.slice(5)]:event.target.value};}else next.conditions=conditionValue(options.querySelector('.advanced-asset-conditions'));updateSettings(next);api.render();});panel.prepend(options);
                    if(config.assetKind==='form'){drawFormFields();$('#advanced-add-field').addEventListener('click',event=>{event.preventDefault();const next=settings();next.asset.form ||= {};next.asset.form.fields ||= [];next.asset.form.fields.push({name:'field_'+(next.asset.form.fields.length+1),label:'Your question',type:'text',required:false,options:[]});updateSettings(next);drawFormFields();api.render();});}
                }
                panel.querySelectorAll('[data-publish-tool]').forEach(button=>button.addEventListener('click',()=>publishing(button.dataset.publishTool)));
                $('#advanced-audit').addEventListener('click',audit);
                api.command('assets').then(data=>{const current=data.workflow;$('#advanced-workflow-status').textContent=current?'Publication: '+current.state+(current.scheduled_at?' · '+current.scheduled_at+' UTC':'')+(current.note?' · '+current.note:''):'';}).catch(error=>notice(error.message,true));
            }
            function drawFormFields(){const root=$('#advanced-form-fields');if(!root)return;const fields=settings().asset?.form?.fields||[];root.innerHTML=fields.map((item,index)=>`<fieldset data-form-field="${index}"><legend>Additional field ${index+1}</legend>${field('Name',`<input data-field-part="name" value="${escape(item.name)}">`)}${field('Label',`<input data-field-part="label" value="${escape(item.label)}">`)}${field('Type',`<select data-field-part="type">${['text','textarea','select','checkbox','number','date','email','tel'].map(type=>`<option ${item.type===type?'selected':''}>${type}</option>`).join('')}</select>`)}${field('Options (one per line)',`<textarea data-field-part="options">${escape((item.options||[]).join('\n'))}</textarea>`)}<label><input data-field-part="required" type="checkbox" ${item.required?'checked':''}> Required</label><button data-delete-field="${index}">Remove field</button></fieldset>`).join('');root.onchange=event=>{const box=event.target.closest('[data-form-field]');if(!box)return;const next=settings(),item=next.asset.form.fields[Number(box.dataset.formField)],part=event.target.dataset.fieldPart;if(!part)return;item[part]=part==='required'?event.target.checked:part==='options'?event.target.value.split('\n').filter(Boolean):event.target.value;updateSettings(next);api.render();};root.onclick=event=>{const button=event.target.closest('[data-delete-field]');if(!button)return;event.preventDefault();const next=settings();next.asset.form.fields.splice(Number(button.dataset.deleteField),1);updateSettings(next);drawFormFields();api.render();};}
            async function publishing(action) {
                try {
                    if(action==='compare'){const before=await api.command('published'),after=await api.command('render',{payload:JSON.stringify(api.getPayload())});const head=api.frame().head.cloneNode(true);head.querySelector('#builder-preview-styles')?.remove();show('Compare with published content','<div class="advanced-compare"><div><h3>Published</h3><iframe title="Published page comparison" sandbox="allow-same-origin"></iframe></div><div><h3>Working draft</h3><iframe title="Draft page comparison" sandbox="allow-same-origin"></iframe></div></div><details><summary>Content and setting changes</summary><pre>'+escape(JSON.stringify({published:before.payload,draft:api.getPayload()},null,2))+'</pre></details>');modal.querySelectorAll('iframe').forEach((frame,index)=>{const data=index?after:before;const html=config.styleOnly?api.frame().querySelector(config.selector).outerHTML:data.html;frame.srcdoc='<!doctype html><html><head>'+head.innerHTML+'<style>'+ (data.css||'')+'</style></head><body>'+html+'</body></html>';});return;}
                    if(action==='cancel'){await api.command('cancel_schedule');notice('Scheduled publication cancelled.');pagePanel();return;}
                    await api.save();
                    if(action==='review'){await api.command('submit_review');notice('Draft submitted for review. A publisher can open it from the Library review queue.');}
                    if(action==='schedule'){const when=prompt('Publication date and time in UTC (YYYY-MM-DDTHH:MM)');if(!when)return;await api.command('schedule',{scheduled_at:when});notice('Publication scheduled. Changing the saved draft afterwards will prevent that scheduled version from publishing.');}
                    pagePanel();
                }catch(error){notice(error.message,true);}
            }
            function audit(){const root=api.source(),issues=[];root.querySelectorAll('img').forEach(image=>{if(!image.hasAttribute('alt')||!image.alt.trim())issues.push('Add an image description or mark a decorative image with an empty description.');});let last=1;root.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(heading=>{const level=Number(heading.tagName[1]);if(level>last+1)issues.push('Heading levels jump from H'+last+' to H'+level+'.');last=level;});root.querySelectorAll('a,button').forEach(link=>{if(!link.textContent.trim()&&!link.getAttribute('aria-label')&&!link.querySelector('img[alt]'))issues.push('A link or button needs an accessible label.');});if(!api.getPayload().meta_description)issues.push('Add an SEO description.');const frame=api.frame();if(frame){const rgb=value=>{const match=value.match(/rgba?\(([^)]+)\)/);return match?match[1].split(',').map(Number):null;};const luminance=color=>color.slice(0,3).map(value=>{value/=255;return value<=0.04045?value/12.92:((value+0.055)/1.055)**2.4;}).reduce((sum,value,index)=>sum+value*[0.2126,0.7152,0.0722][index],0);frame.querySelectorAll('[data-cms-node]').forEach(node=>{if(!/^(P|H[1-6]|A|BUTTON|LI)$/.test(node.tagName)||!node.textContent.trim())return;const style=frame.defaultView.getComputedStyle(node);if(style.display==='none'||style.visibility==='hidden')return;const fg=rgb(style.color);let bg=null,parent=node;for(let i=0;parent&&i<15;i++,parent=parent.parentElement){const value=rgb(frame.defaultView.getComputedStyle(parent).backgroundColor);if(value&&(value.length<4||value[3]===1)){bg=value;break;}}if(!fg||!bg)return;const values=[luminance(fg),luminance(bg)].sort((a,b)=>b-a),ratio=(values[0]+0.05)/(values[1]+0.05),large=parseFloat(style.fontSize)>=24||(parseFloat(style.fontSize)>=18.66&&Number(style.fontWeight)>=700);if(ratio<(large?3:4.5))issues.push('Review text contrast near “'+node.textContent.trim().slice(0,45)+'” ('+ratio.toFixed(1)+':1).');});}const count=root.querySelectorAll('*').length;if(count>800)issues.push('This page contains '+count+' elements. Consider simplifying repeated layouts.');const doc=api.frame();if(doc){doc.querySelectorAll('[data-cms-node]').forEach(node=>{if(node.tagName==='IMG'&&node.naturalWidth>2000)issues.push('A large original image is being displayed. Choose an appropriately sized asset.');});}$('#advanced-audit-results').innerHTML=issues.length?'<ul>'+[...new Set(issues)].map(issue=>'<li>'+escape(issue)+'</li>').join('')+'</ul>':'<p>No issues found by these basic checks. Keyboard and screen-reader review are separate.</p>';}
            async function loadAssets(){const data=await api.command('assets');assets=data.assets||[];if(config.blogWidget){const card=config.blogWidget.fields.find(field=>field.name==='card_template');if(card)card.options={'':'Theme card layout',...Object.fromEntries(assets.filter(item=>item.kind==='card'&&item.published).map(item=>[item.key,item.title]))};}return assets;}
            async function openLibrary(){show('Design library','<p>Loading saved designs…</p>');try{await loadAssets();drawLibrary();}catch(error){notice(error.message,true);modal.close();}}
            function drawLibrary() {
                show('Design library',`<div class="advanced-button-row"><a href="${escape(config.advanced.libraryUrl)}" target="_blank" rel="noopener">Manage library ↗</a>${designer?'<button id="advanced-new-design">Create design</button>':''}</div><div class="advanced-library-grid">${assets.map((item,index)=>`<article><small>${escape(item.kind)} · ${item.published?'Published':'Draft'}</small><h3>${escape(item.title)}</h3><div>${['section','template','card'].includes(item.kind)?`<button data-copy-asset="${index}" ${designer?'':'disabled'}>Insert copy</button>`:''}${['section','form'].includes(item.kind)?`<button data-link-asset="${index}" ${item.published&&designer?'':'disabled title="Publish this design first"'}>Insert linked block</button>`:''}<a href="${escape(item.url)}" target="_blank" rel="noopener">Edit ↗</a></div></article>`).join('')||'<p>No saved designs yet.</p>'}</div>`);
                modal.querySelectorAll('[data-copy-asset]').forEach(button=>button.addEventListener('click',()=>{
                    const item=assets[Number(button.dataset.copyAsset)], design=item.payload.builder||{};
                    insert('<div class="'+escape(design.classes||'')+'" data-builder-css="'+escape(design.css||'')+'" data-builder-conditions="'+escape(JSON.stringify(design.conditions||{}))+'">'+item.payload.html+'</div>');
                    modal.close();
                }));
                modal.querySelectorAll('[data-link-asset]').forEach(button=>button.addEventListener('click',()=>{const item=assets[Number(button.dataset.linkAsset)];insert('<section data-builder-ref="'+item.key+'"></section>');modal.close();}));
                $('#advanced-new-design')?.addEventListener('click',()=>saveAsset(null,'section'));
            }
            async function saveAsset(html,kind) {
                if(!designer)return;
                const title=prompt('Name for this reusable design');
                if(!title)return;
                try {
                    const current=api.getPayload(), design=current.builder||{};
                    const values=html===null?{}:{asset_payload:JSON.stringify({...current,html,title,builder:{...defaults(),css:design.css||'',classes:kind==='template'?(design.classes||''):'',asset:{kind,target:kind==='template'?'none':'all'}}})};
                    const data=await api.command('create_asset',{kind,title,...values});
                    notice('Design saved as a draft. Open it in the Library to publish or reuse it.');
                    window.open(data.url,'_blank','noopener');
                } catch(error) { notice(error.message,true); }
            }
            function download(value,name){const url=URL.createObjectURL(new Blob([JSON.stringify(value,null,2)],{type:'application/json'}));const anchor=document.createElement('a');anchor.href=url;anchor.download=name;anchor.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
            const additions = {grid:['Grid container','<div class="builder-grid" data-builder-styles=\'{"desktop":{"display":"grid","grid-template-columns":"repeat(3,minmax(0,1fr))","gap":"24px"},"tablet":{"grid-template-columns":"repeat(2,minmax(0,1fr))"},"mobile":{"grid-template-columns":"1fr"}}\'><div><h3>First column</h3><p>Your content.</p></div><div><h3>Second column</h3><p>Your content.</p></div><div><h3>Third column</h3><p>Your content.</p></div></div>'],flex:['Flex container','<div class="builder-flex" data-builder-styles=\'{"desktop":{"display":"flex","gap":"24px","flex-wrap":"wrap"}}\'><div><h3>Flexible item</h3><p>Add your content.</p></div><div><h3>Another item</h3><p>Add your content.</p></div></div>'],tabs:['Tabs','<div data-builder-widget="tabs"><section><h3>First tab</h3><p>Your first panel.</p></section><section><h3>Second tab</h3><p>Your second panel.</p></section></div>'],gallery:['Gallery & lightbox','<div class="builder-gallery" data-builder-widget="gallery"><a href="/services/assets/home-sample.jpg"><img src="/services/assets/home-sample.jpg" alt="Illustrative living room"></a><a href="/services/assets/workplace-sample.jpg"><img src="/services/assets/workplace-sample.jpg" alt="Illustrative workplace"></a></div>'],video:['Video','<div data-builder-widget="video"><p>Choose a YouTube or Vimeo URL in Advanced element settings.</p></div>'],icons:['Icon list','<ul class="builder-icon-list" data-builder-widget="icons"><li><span aria-hidden="true">✓</span> Your first feature</li><li><span aria-hidden="true">✓</span> Your second feature</li></ul>'],navigation:['Navigation','<nav class="builder-navigation" data-builder-widget="navigation" aria-label="Section navigation"><a href="/shop">Shop</a><a href="/services">Services</a><a href="/blog">Advice</a></nav>'],slot:['Page content slot','<div data-builder-slot="content"></div>']};
            if(designer)Object.entries(additions).forEach(([key,[label,html]])=>{const button=document.createElement('button');button.innerHTML='<strong>＋</strong><span>'+escape(label)+'</span>';button.addEventListener('click',()=>{const wrapper='<section class="section container" data-builder-node="'+random()+'">'+html+'</section>';insert(wrapper);$('#editor-add-dialog').close();});$('.editor-template-grid').append(button);});
            if(!designer){$('#editor-add').disabled=true;host.classList.add('builder-content-only');}
            const keyboard = event=>{if(!(event.ctrlKey||event.metaKey)||event.target.closest('input,textarea,[contenteditable=true]'))return;if(event.key.toLowerCase()==='z'){event.preventDefault();history(event.shiftKey?'redo':'undo');}else if(event.key.toLowerCase()==='y'){event.preventDefault();history('redo');}};
            document.addEventListener('keydown',keyboard);
            return {
                changed,inspector,structure,cloneIds,toggle,
                loaded(){if(applying)return;lastSnapshot=JSON.stringify(api.getPayload());multi.clear();structure();},
                rendered(documentCopy){resize(api.selected());if(!documentCopy.builderKeyboardReady){documentCopy.addEventListener('keydown',keyboard);documentCopy.builderKeyboardReady=true;}window.Shi3ldBuilderRuntime?.mount(documentCopy,{preview:true});},
                selection(){if(!multi.has(api.selected()?.dataset.cmsNode))multi.clear();},
                device(next){device=next;api.inspect();},
            };
        }
    };
}());
