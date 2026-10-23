<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

class Schema
{
    public const KINDS = ['section', 'template', 'global', 'card', 'popup', 'form', 'product'];

    public const TARGETS = ['none', 'all', 'cms', 'article', 'category', 'services', 'header', 'footer', 'product'];

    public const FIELDS = ['title', 'excerpt', 'image', 'url', 'author', 'date', 'category', 'product.name', 'product.sku', 'product.price', 'product.image', 'product.url', 'service.title', 'service.summary', 'service.url'];

    public const PROPERTIES = ['color', 'background-color', 'font-size', 'font-family', 'font-weight', 'line-height', 'letter-spacing', 'text-align', 'text-decoration', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'width', 'max-width', 'min-height', 'height', 'display', 'grid-template-columns', 'gap', 'column-gap', 'row-gap', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-self', 'flex-basis', 'flex-grow', 'order', 'border', 'border-color', 'border-width', 'border-style', 'border-radius', 'box-shadow', 'opacity', 'object-fit', 'object-position', 'aspect-ratio', 'position', 'top', 'z-index', 'background-image', 'overflow', 'transition', 'transform'];

    public function __construct(private Css $css) {}

    public function settings(array $input): array
    {
        $result = ['css' => $this->text($input['css'] ?? '', 65536), 'classes' => $this->text($input['classes'] ?? '', 300),
            'asset' => [], 'theme' => [], 'conditions' => $this->conditions($input['conditions'] ?? []),
            'has_audience_rules' => ! empty($input['has_audience_rules']), 'has_date_rules' => ! empty($input['has_date_rules'])];
        if ($result['classes'] !== '' && ! preg_match('/^[a-zA-Z0-9_ -]+$/D', $result['classes'])) {
            throw new \InvalidArgumentException('Use ordinary CSS class names separated by spaces.');
        }
        $this->css->compile($result['css'], '[data-builder-region="validation"]');
        foreach (($input['theme'] ?? []) as $name => $value) {
            if (! in_array($name, ['primary', 'secondary', 'accent', 'text', 'background', 'font', 'heading_font', 'radius', 'content_width', 'spacing', 'button_padding'], true)) {
                throw new \InvalidArgumentException('Unknown global design setting.');
            }
            $value = $this->text($value, 150);
            if (preg_match('/[{};@<>\\\\]|url\s*\(|expression\s*\(/i', $value)) {
                throw new \InvalidArgumentException('Enter an ordinary design value.');
            }
            $result['theme'][$name] = $value;
        }
        if (isset($input['asset']) && $input['asset'] !== []) {
            $asset = (array) $input['asset'];
            $result['asset'] = ['kind' => $this->choice($asset['kind'] ?? 'section', self::KINDS),
                'target' => $this->choice($asset['target'] ?? (($asset['kind'] ?? '') === 'template' ? 'none' : 'all'), self::TARGETS),
                'priority' => max(0, min(1000, (int) ($asset['priority'] ?? 10))),
                'trigger' => $this->choice($asset['trigger'] ?? 'delay', ['delay', 'scroll', 'click', 'banner']),
                'delay' => max(0, min(300, (int) ($asset['delay'] ?? 5))),
                'scroll' => max(1, min(100, (int) ($asset['scroll'] ?? 60))),
                'frequency' => $this->choice($asset['frequency'] ?? 'session', ['session', 'day', 'always']),
                'product_layout' => $this->choice($asset['product_layout'] ?? 'gallery-left', ['gallery-left', 'gallery-right', 'gallery-top']),
                'form' => $this->form($asset['form'] ?? [])];
        }

        return $result;
    }

    public function conditions(array $input): array
    {
        $result = ['include' => $this->text($input['include'] ?? '', 1000), 'exclude' => $this->text($input['exclude'] ?? '', 1000),
            'audience' => $this->choice($input['audience'] ?? 'all', ['all', 'guest', 'customer', 'staff']),
            'start' => $this->text($input['start'] ?? '', 30), 'end' => $this->text($input['end'] ?? '', 30)];
        foreach (['include', 'exclude'] as $name) {
            foreach (array_filter(array_map('trim', explode(',', $result[$name]))) as $path) {
                if (! preg_match('~^/[a-zA-Z0-9_./*% -]*$~D', $path) || str_contains($path, '..')) {
                    throw new \InvalidArgumentException('Display rules use website paths, such as /services/* or /blog/*.');
                }
            }
        }
        foreach (['start', 'end'] as $name) {
            if ($result[$name] !== '' && (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?Z?$/D', $result[$name]) || strtotime($result[$name].(str_ends_with($result[$name], 'Z') ? '' : ' UTC')) === false)) {
                throw new \InvalidArgumentException('Enter a valid UTC display date and time.');
            }
        }
        if ($result['start'] && $result['end'] && strtotime($result['start']) >= strtotime($result['end'])) {
            throw new \InvalidArgumentException('The end time must follow the start time.');
        }

        return $result;
    }

    public function styles(array $input): array
    {
        $result = [];
        foreach ($input as $device => $properties) {
            if (! in_array($device, ['desktop', 'tablet', 'mobile', 'hover', 'focus'], true) || ! is_array($properties)) {
                throw new \InvalidArgumentException('Choose a valid responsive style group.');
            }
            foreach ($properties as $property => $value) {
                if (! in_array($property, self::PROPERTIES, true)) {
                    throw new \InvalidArgumentException('Unknown appearance property: '.$property);
                }
                $value = $this->text($value, 300);
                if ($value === '') {
                    continue;
                }
                $this->css->compile('selector{'.$property.':'.$value.'}', '[data-builder-node="validation"]');
                if ($value !== '') {
                    $result[$device][$property] = $value;
                }
            }
        }
        $ordered = [];
        foreach (['desktop', 'tablet', 'mobile', 'hover', 'focus'] as $device) {
            if (isset($result[$device])) {
                $ordered[$device] = $result[$device];
            }
        }

        return $ordered;
    }

    public function portableHtml(string $html): string
    {
        $html = preg_replace_callback('~\{\{(store|media)\s+url\s*=\s*[\x22\x27]?([^\x22\x27}]*?)[\x22\x27]?\s*\}\}~', fn ($m) => ($m[1] === 'media' ? '/media/' : '/').ltrim(trim($m[2]), '/'), $html);

        return preg_replace_callback('~\{\{widget\s+type="(Shi3ld\\\\Blog\\\\Block\\\\Widget\\\\Articles|Magento\\\\CatalogWidget\\\\Block\\\\Product\\\\ProductsList)".*?\}\}~s', function (array $match): string {
            $kind = str_contains($match[1], 'Shi3ld') ? 'blog' : 'products';
            preg_match_all('/\b([a-z_]+)="([^"<>]*)"/', $match[0], $attributes, PREG_SET_ORDER);
            $options = [];
            foreach ($attributes as $attribute) {
                if ($attribute[1] !== 'type') {
                    $options[$attribute[1]] = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
            if ($kind === 'products') {
                $options = ['limit' => (int) ($options['products_count'] ?? 6)];
            }

            return '<div data-builder-remote-widget="'.$kind.'" data-builder-remote-options="'.htmlspecialchars(json_encode($options, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8').'"></div>';
        }, $html);
    }

    public function attributes(\DOMDocument $document): void
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            foreach (['data-builder-node' => '/^[a-f0-9]{16}$/D', 'data-builder-ref' => '/^[a-f0-9]{32}$/D', 'data-builder-label' => '/^[^<>]{0,100}$/uD',
                'data-builder-lock' => '/^(?:0|1)$/D', 'data-builder-animation' => '/^(?:none|fade|rise|slide)$/D', 'data-builder-slot' => '/^(?:content|gallery|information|details|related)$/D',
                'data-builder-widget' => '/^(?:tabs|gallery|video|icons|navigation|form)$/D'] as $name => $pattern) {
                if ($element->hasAttribute($name) && ! preg_match($pattern, $element->getAttribute($name))) {
                    throw new \InvalidArgumentException('Invalid builder element setting: '.$name);
                }
            }
            if ($element->hasAttribute('data-builder-field') && ! in_array($element->getAttribute('data-builder-field'), self::FIELDS, true)) {
                throw new \InvalidArgumentException('Choose an approved dynamic field.');
            }
            if ($element->hasAttribute('data-builder-remote-widget') && ! in_array($element->getAttribute('data-builder-remote-widget'), ['blog', 'products'], true)) {
                throw new \InvalidArgumentException('Choose an approved shared widget.');
            }
            if ($element->hasAttribute('data-builder-remote-options')) {
                $json = $element->getAttribute('data-builder-remote-options');
                if (strlen($json) > 6000) {
                    throw new \InvalidArgumentException('The shared widget settings are too large.');
                }
                $options = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
                if (! is_array($options) || count($options) > 40) {
                    throw new \InvalidArgumentException('Enter valid shared widget settings.');
                }
                foreach ($options as $name => $value) {
                    if (! is_string($name) || ! preg_match('/^[a-z_]{1,40}$/D', $name) || ! is_scalar($value) || strlen((string) $value) > 1000) {
                        throw new \InvalidArgumentException('Enter valid shared widget settings.');
                    }
                }
            }
            foreach (['data-builder-styles', 'data-builder-conditions'] as $name) {
                if (! $element->hasAttribute($name)) {
                    continue;
                }
                $value = json_decode($element->getAttribute($name), true, 16, JSON_THROW_ON_ERROR);
                if (! is_array($value)) {
                    throw new \InvalidArgumentException('This element has invalid settings.');
                }
                $value = $name === 'data-builder-styles' ? $this->styles($value) : $this->conditions($value);
                $element->setAttribute($name, json_encode($value, JSON_THROW_ON_ERROR));
            }
            if ($element->hasAttribute('data-builder-css')) {
                $this->css->compile($element->getAttribute('data-builder-css'), '[data-builder-node="validation"]');
            }
            if ($element->hasAttribute('data-builder-background')) {
                $this->mediaUrl($element->getAttribute('data-builder-background'));
            }
            if ($element->hasAttribute('data-builder-video') && ! preg_match('~^https://(?:www\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/)[a-zA-Z0-9_-]+|youtu\.be/[a-zA-Z0-9_-]+|vimeo\.com/(?:video/)?[0-9]+)$~D', $element->getAttribute('data-builder-video'))) {
                throw new \InvalidArgumentException('Use a YouTube or Vimeo video URL.');
            }
        }
    }

    public function templateContent(array $settings, string $html): void
    {
        $asset = $settings['asset'] ?? [];
        if (($asset['kind'] ?? '') !== 'template' || in_array($asset['target'] ?? 'none', ['none', 'header', 'footer'], true)) {
            return;
        }
        preg_match_all('/data-builder-slot\s*=\s*[\x22\x27]content[\x22\x27]/', $html, $matches);
        if (count($matches[0]) !== 1) {
            throw new \InvalidArgumentException('An applied page template needs exactly one Page content slot. Use Library only for a reusable page copy.');
        }
    }

    public function mediaUrl(string $url): string
    {
        if (! preg_match('~^/(?:media/|services/assets/)[a-zA-Z0-9_./% -]+\.(?:png|jpe?g|gif|webp|svg)$~iD', $url) || str_contains($url, '..')) {
            throw new \InvalidArgumentException('Choose an image from the media gallery.');
        }

        return $url;
    }

    public function matches(array $conditions, array $context): bool
    {
        $rules = $this->conditions($conditions);
        $path = $context['path'] ?? '/';
        foreach (['include', 'exclude'] as $key) {
            $patterns = array_filter(array_map('trim', explode(',', $rules[$key])));
            $match = ! $patterns;
            foreach ($patterns as $pattern) {
                if (preg_match('~^'.str_replace('\*', '.*', preg_quote($pattern, '~')).'$~D', $path)) {
                    $match = true;
                    break;
                }
            }
            if (($key === 'include' && ! $match) || ($key === 'exclude' && $patterns && $match)) {
                return false;
            }
        }
        if ($rules['audience'] !== 'all' && $rules['audience'] !== ($context['audience'] ?? 'guest')) {
            return false;
        }
        foreach (['start', 'end'] as $key) {
            if ($rules[$key] === '') {
                continue;
            }
            $time = strtotime($rules[$key].(str_ends_with($rules[$key], 'Z') ? '' : ' UTC'));
            if (($key === 'start' && time() < $time) || ($key === 'end' && time() >= $time)) {
                return false;
            }
        }

        return true;
    }

    private function form(array $input): array
    {
        $result = ['service_type' => $this->choice($input['service_type'] ?? 'domestic', ['domestic', 'commercial', 'care_home']),
            'button' => $this->text($input['button'] ?? 'Send enquiry', 80), 'success' => $this->text($input['success'] ?? 'Thank you. Your enquiry has been received.', 500), 'fields' => []];
        if (count($input['fields'] ?? []) > 30) {
            throw new \InvalidArgumentException('A form can contain up to 30 additional fields.');
        }
        $names = [];
        foreach ($input['fields'] ?? [] as $field) {
            $name = $this->text($field['name'] ?? '', 40);
            if (! preg_match('/^[a-z][a-z0-9_]*$/D', $name) || isset($names[$name])) {
                throw new \InvalidArgumentException('Form field names must be unique lowercase names.');
            }
            $names[$name] = true;
            $result['fields'][] = ['name' => $name, 'label' => $this->text($field['label'] ?? $name, 150),
                'type' => $this->choice($field['type'] ?? 'text', ['text', 'textarea', 'select', 'checkbox', 'number', 'date', 'email', 'tel']),
                'required' => ! empty($field['required']), 'options' => array_slice(array_map(fn ($value) => $this->text($value, 100), $field['options'] ?? []), 0, 30)];
        }

        return $result;
    }

    private function text(mixed $value, int $max): string
    {
        if (! is_string($value) || mb_strlen($value) > $max || ! mb_check_encoding($value, 'UTF-8')) {
            throw new \InvalidArgumentException('A builder setting contains invalid or oversized text.');
        }

        return trim($value);
    }

    private function choice(mixed $value, array $allowed): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('Choose a valid builder option.');
        }

        return $value;
    }
}
