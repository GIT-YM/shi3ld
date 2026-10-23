(function () {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const decode = value => { const element = document.createElement('textarea'); element.innerHTML = value; return element.value; };
    function render(fields, values = {}) {
        return fields.map(field => {
            const value = values[field.name] ?? field.default;
            const attributes = `data-blog-field="${escape(field.name)}"`;
            if (field.type === 'checkbox') return `<label class="editor-field blog-control-checkbox"><input type="checkbox" ${attributes} ${Number(value) ? 'checked' : ''}>${escape(field.label)}</label>`;
            let control;
            if (field.type === 'select' || field.type === 'multiselect') {
                const options = Array.isArray(field.options) ? field.options : Object.entries(field.options).map(([value,label]) => ({value,label}));
                const chosen = String(value).split(',');
                control = `<select ${attributes} ${field.type === 'multiselect' ? 'multiple size="5"' : ''}>${options.map(option => `<option value="${escape(option.value)}" ${chosen.includes(String(option.value)) ? 'selected' : ''}>${escape(option.label)}</option>`).join('')}</select>`;
            } else control = `<input ${attributes} type="${field.type === 'number' ? 'number' : 'text'}" value="${escape(value)}" ${field.type === 'number' ? `min="${field.min}" max="${field.max}"` : `maxlength="${field.max}"`}>`;
            return `<label class="editor-field">${escape(field.label)}${control}</label>`;
        }).join('');
    }
    function read(root, fields) {
        const result = {};
        fields.forEach(field => {
            const element = root.querySelector('[data-blog-field="' + field.name + '"]');
            if (!element) return;
            const number = element.value === '' || !Number.isFinite(Number(element.value)) ? field.default : Number(element.value);
            result[field.name] = field.type === 'checkbox' ? (element.checked ? 1 : 0) : field.type === 'multiselect' ? [...element.selectedOptions].map(option => option.value).join(',') : field.type === 'number' ? Math.min(field.max, Math.max(field.min, number)) : element.value;
        });
        return result;
    }
    function directive(config, values) {
        const attributes = config.fields.map(field => ` ${field.name}="${encodeURIComponent(String(values[field.name] ?? field.default))}"`).join('');
        return '{{widget type="' + config.class + '" encoded="1"' + attributes + '}}';
    }
    function parse(config, text) {
        const match = text.match(/\{\{widget\s+(.+?)\}\}/s);
        if (!match) return null;
        const values = {};
        for (const attribute of match[1].matchAll(/([a-z_]+)="([^"]*)"/g)) values[attribute[1]] = decode(attribute[2]);
        if (values.type !== config.class) return null;
        if (values.encoded === '1') Object.keys(values).forEach(key => { if (key !== 'type') { try { values[key] = decodeURIComponent(values[key]); } catch (_) {} } });
        return values;
    }
    function canonicalize(config, html) {
        return html.replace(/\{\{widget\s+.+?\}\}/gs, text => { const values = parse(config, text); return values ? directive(config, values) : text; });
    }
    window.Shi3ldBlogControls = {render,read,directive,parse,canonicalize};
}());
