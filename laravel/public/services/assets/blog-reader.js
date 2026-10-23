(function () {
    'use strict';
    const script = document.currentScript;
    const article = document.querySelector('[data-blog-post]');
    const preview = article?.dataset.blogPreview === '1';
    const postId = Number(article?.dataset.blogPost || 0);
    const subscriptions = [...document.querySelectorAll('[data-blog-subscription]')];
    const management = document.querySelector('[data-blog-manage]');
    const tracking = navigator.doNotTrack !== '1' && !navigator.globalPrivacyControl;
    const contextUrl = script?.dataset.blogContextUrl || '/blog/reader/context';
    const submitUrl = script?.dataset.blogSubmitUrl || '/blog/interaction/submit';
    let context, loadedAt = 0, loading;
    const status = (element, message) => { if (element) element.textContent = message; };
    const pause = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

    async function loadContext() {
        if (loading) return loading;
        loading = (async () => {
            const url = new URL(contextUrl, location.origin);
            url.searchParams.set('post_id', String(postId));
            const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || 'The reader tools could not be loaded.');
            context = data; loadedAt = Date.now(); return data;
        })();
        try { return await loading; } finally { loading = null; }
    }

    async function submit(intent, fields = {}, form = null, keepalive = false) {
        if (!context || Date.now() - loadedAt > 1500000) await loadContext();
        const body = form ? new FormData(form) : new FormData();
        body.set('form_key', context.csrf); body.set('nonce', context.nonce);
        body.set('intent', intent); body.set('post_id', String(postId));
        Object.entries(fields).forEach(([name, value]) => body.set(name, String(value)));
        if (intent === 'comment' || intent === 'subscribe') {
            const issued = Number(context.nonce.split('.')[1]) * 1000;
            await pause(Math.min(3200, Math.max(0, issued + 3100 - Date.now())));
        }
        const response = await fetch(submitUrl, {method:'POST', credentials:'same-origin', body, keepalive, redirect:'error', headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'This request could not be completed. Refresh the page and try again.');
        return data;
    }

    function topics(root, chosen = []) {
        if (!root) return;
        root.replaceChildren();
        context.categories.forEach(category => {
            const label = document.createElement('label'), box = document.createElement('input');
            box.type = 'checkbox'; box.name = 'category_ids[]'; box.value = String(category.id);
            box.checked = chosen.map(Number).includes(Number(category.id));
            label.append(box, document.createTextNode(' ' + category.title)); root.append(label);
        });
    }

    function bindForm(form, intent) {
        if (!form) return;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('[type=submit]'), output = form.querySelector('[data-form-status]');
            if (button.disabled) return;
            button.disabled = true; status(output, 'Sending…');
            try {
                const result = await submit(intent, {}, form);
                status(output, result.message);
                if (['subscribe','comment'].includes(intent)) form.reset();
                if (intent === 'confirm') return;
            } catch (error) { status(output, error.message); }
            finally { if (intent !== 'confirm' || !output.textContent.includes('confirmed')) button.disabled = false; }
        });
    }

    if (article) {
        const shareStatus = document.querySelector('[data-blog-share-status]');
        const canonical = document.querySelector('link[rel=canonical]')?.href || location.origin + location.pathname;
        document.querySelector('[data-blog-copy]')?.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(canonical); status(shareStatus, 'Article link copied.'); }
            catch (_) { status(shareStatus, 'Copy this link: ' + canonical); }
        });
        document.querySelector('[data-blog-share]')?.addEventListener('click', async () => {
            try {
                if (navigator.share) await navigator.share({title:document.querySelector('meta[property="og:title"]')?.content || document.title, url:canonical});
                else { await navigator.clipboard.writeText(canonical); status(shareStatus, 'Article link copied for sharing.'); }
            } catch (error) { if (error.name !== 'AbortError') status(shareStatus, 'Copy this link: ' + canonical); }
        });
        const progress = article.querySelector('[role=progressbar]'), copy = article.querySelector('.blog-article-body');
        if (progress && copy) {
            let scheduled = false;
            const update = () => {
                const bounds = copy.getBoundingClientRect();
                const value = Math.round(Math.max(0, Math.min(100, (innerHeight - bounds.top) / Math.max(1, bounds.height) * 100)));
                progress.setAttribute('aria-valuenow', String(value)); progress.querySelector('span').style.width = value + '%'; scheduled = false;
            };
            const queue = () => { if (!scheduled) { scheduled = true; requestAnimationFrame(update); } };
            addEventListener('scroll', queue, {passive:true}); addEventListener('resize', queue); update();
        }
    }
    if (preview || (!article && !subscriptions.length && !management)) return;

    loadContext().then(data => {
        const reader = document.querySelector('[data-blog-reader]');
        if (reader) {
            reader.querySelectorAll('[data-blog-vote]').forEach(button => {
                const yes = button.dataset.blogVote === 'yes';
                const count = data.stats[yes ? 'helpful_yes' : 'helpful_no'] || 0;
                button.textContent = (yes ? 'Yes' : 'No') + (count ? ' (' + count + ')' : '');
                button.addEventListener('click', async () => {
                    const buttons = reader.querySelectorAll('[data-blog-vote]'); buttons.forEach(item => { item.disabled = true; });
                    try { const result = await submit('event', {event:yes ? 'helpful_yes' : 'helpful_no'}); status(reader.querySelector('[data-blog-reader-status]'), result.message); }
                    catch (error) { status(reader.querySelector('[data-blog-reader-status]'), error.message); buttons.forEach(item => { item.disabled = false; }); }
                });
            });
            const comments = reader.querySelector('[data-blog-comments]');
            if (comments && data.comments_enabled) {
                comments.hidden = false;
                const list = comments.querySelector('[data-blog-comment-list]');
                data.comments.forEach(item => {
                    const entry = document.createElement('article'), heading = document.createElement('h3'), date = document.createElement('time'), body = document.createElement('p');
                    heading.textContent = item.name; body.textContent = item.body;
                    date.dateTime = item.created_at.replace(' ', 'T') + 'Z'; date.textContent = new Date(date.dateTime).toLocaleDateString(document.documentElement.lang || 'en-GB');
                    entry.append(heading, date, body); list.append(entry);
                });
                bindForm(comments.querySelector('form'), 'comment');
            }
        }
        subscriptions.forEach(section => {
            if (!data.subscriptions_enabled) return;
            section.hidden = false; topics(section.querySelector('[data-blog-subscription-topics]'));
            bindForm(section.querySelector('form'), 'subscribe');
        });
        if (management) {
            let chosen = []; try { chosen = JSON.parse(management.dataset.blogSelectedTopics || '[]'); } catch (_) {}
            topics(management.querySelector('[data-blog-subscription-topics]'), chosen);
            management.querySelectorAll('button').forEach(button => { button.disabled = false; });
            bindForm(management, management.dataset.blogManage === 'confirm' ? 'confirm' : 'manage');
            management.querySelector('[data-blog-unsubscribe]')?.addEventListener('click', async event => {
                event.target.disabled = true;
                try { const result = await submit('unsubscribe', {}, management); status(management.querySelector('[data-form-status]'), result.message); management.querySelectorAll('button').forEach(button => { button.disabled = true; }); }
                catch (error) { status(management.querySelector('[data-form-status]'), error.message); event.target.disabled = false; }
            });
        }
        if (article && tracking) {
            const view = () => { if (document.visibilityState === 'visible') submit('event', {event:'view'}).catch(() => {}); };
            if (document.visibilityState === 'visible') setTimeout(view, 1000);
            else document.addEventListener('visibilitychange', view, {once:true});
            article.querySelectorAll('[data-blog-products] a,[data-blog-services] a').forEach(link => link.addEventListener('click', () => {
                submit('event', {event:link.closest('[data-blog-products]') ? 'product_click' : 'service_click'}, null, true).catch(() => {});
            }));
        }
    }).catch(error => {
        status(document.querySelector('[data-blog-reader-status]'), error.message);
        status(management?.querySelector('[data-form-status]'), error.message);
    });
}());
