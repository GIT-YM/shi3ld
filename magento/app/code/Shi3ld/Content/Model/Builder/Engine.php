<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

class Engine
{
    public function __construct(private Css $css, private Schema $schema) {}

    public function scope(string $key): string
    {
        return 'b-'.substr(hash('sha256', $key), 0, 20);
    }

    public function render(string $html, array $settings, array $context, callable $asset, bool $editing = false, array $stack = []): array
    {
        if (count($stack) > 8) {
            throw new \InvalidArgumentException('Reusable blocks contain a circular or deeply nested reference.');
        }
        $key = $this->scope($context['key'] ?? 'page');
        $scope = '[data-builder-region="'.$key.'"]';
        $settings = $this->schema->settings($settings);
        if (! $editing && ! $this->schema->matches($settings['conditions'], $context)) {
            return ['html' => '', 'css' => ''];
        }
        $css = $this->css->compile($settings['css'], $scope);
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><div id="builder-render-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $document->getElementById('builder-render-root');
            $elements = iterator_to_array($root->getElementsByTagName('*'));
            foreach ($elements as $element) {
                for ($parent = $element; $parent !== null && $parent !== $root; $parent = $parent->parentNode) {
                }
                if ($parent !== $root) {
                    continue;
                }
                if ($element->hasAttribute('data-builder-ref')) {
                    $ref = $element->getAttribute('data-builder-ref');
                    if (in_array($ref, $stack, true)) {
                        throw new \InvalidArgumentException('A reusable block cannot include itself.');
                    }
                    $linked = $asset($ref);
                    if (! $linked) {
                        $this->replace($element, $editing ? '<p class="builder-placeholder">Publish the linked block before it appears here.</p>' : '');
                    } elseif ($this->schema->matches($linked['builder']['conditions'] ?? [], $context) || $editing) {
                        if (($linked['builder']['asset']['kind'] ?? '') === 'form') {
                            $this->replace($element, '<div data-builder-form="'.$ref.'"><a href="/services/quote">Open the enquiry form</a></div>');
                        } else {
                            $childContext = array_replace($context, ['key' => ($context['key'] ?? 'page').':'.$ref]);
                            $child = $this->render($linked['html'], $linked['builder'] ?? [], $childContext, $asset, $editing, array_merge($stack, [$ref]));
                            $this->replace($element, $child['html']);
                            $css .= $child['css'];
                        }
                    } else {
                        $this->replace($element, '');
                    }
                }
                if (! $editing && $element->hasAttribute('data-builder-conditions')) {
                    if (! $this->schema->matches(json_decode($element->getAttribute('data-builder-conditions'), true, 16, JSON_THROW_ON_ERROR), $context)) {
                        $element->parentNode?->removeChild($element);

                        continue;
                    }
                }
                if ($element->hasAttribute('data-builder-field')) {
                    $field = $element->getAttribute('data-builder-field');
                    $value = (string) ($context['fields'][$field] ?? '');
                    if ($element->tagName === 'img') {
                        if ($value !== '' && preg_match('~^(?:https://|/(?!/))~', $value) && ! preg_match('/[\x00-\x20"<>]/', $value)) {
                            $element->setAttribute('src', $value);
                        }
                    } elseif ($element->tagName === 'a' && str_ends_with($field, 'url')) {
                        if ($value !== '' && preg_match('~^(?:https://|/(?!/))~', $value) && ! preg_match('/[\x00-\x20"<>]/', $value)) {
                            $element->setAttribute('href', $value);
                        }
                    } else {
                        while ($element->firstChild) {
                            $element->removeChild($element->firstChild);
                        }
                        $element->appendChild($document->createTextNode($value !== '' ? $value : ($editing ? '['.$field.']' : '')));
                    }
                }
                $id = $element->getAttribute('data-builder-node');
                if ($id !== '' && preg_match('/^[a-f0-9]{16}$/D', $id)) {
                    $target = $scope.' [data-builder-node="'.$id.'"]';
                    if ($element->hasAttribute('data-builder-styles')) {
                        $styles = $this->schema->styles(json_decode($element->getAttribute('data-builder-styles'), true, 16, JSON_THROW_ON_ERROR));
                        foreach ($styles as $device => $properties) {
                            $declarations = '';
                            foreach ($properties as $property => $value) {
                                $declarations .= $property.':'.preg_replace('/\s*!important\s*$/i', '', $value).'!important;';
                            }
                            $selector = $target.($device === 'hover' ? ':hover' : ($device === 'focus' ? ':focus-visible' : ''));
                            $rule = $selector.'{'.$declarations.'}';
                            $css .= $device === 'tablet' ? '@media(max-width:1024px){'.$rule.'}' : ($device === 'mobile' ? '@media(max-width:640px){'.$rule.'}' : $rule);
                        }
                    }
                    if ($element->hasAttribute('data-builder-css')) {
                        $css .= $this->css->compile($element->getAttribute('data-builder-css'), $target);
                    }
                    if ($element->hasAttribute('data-builder-background')) {
                        $url = $this->schema->mediaUrl($element->getAttribute('data-builder-background'));
                        $css .= $target.'{background-image:url("'.str_replace(' ', '%20', $url).'");background-size:cover;background-position:center;}';
                    }
                }
            }
            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $classes = $settings['classes'] === '' ? '' : ' class="'.htmlspecialchars($settings['classes'], ENT_QUOTES, 'UTF-8').'"';

        return ['html' => '<div data-builder-region="'.$key.'"'.$classes.'>'.$result.'</div>', 'css' => $css];
    }

    public function wrap(string $html, array $settings, array $context, callable $asset): string
    {
        $result = $this->render($html, $settings, $context, $asset, ! empty($context['editing']));

        return ($result['css'] !== '' ? '<style data-builder-stylesheet="page">'.$result['css'].'</style>' : '').$result['html'];
    }

    public function globalCss(array $settings): string
    {
        $settings = $this->schema->settings($settings);
        $css = $this->css->compile($settings['css'], 'body');
        $names = ['primary' => '--forest', 'secondary' => '--sage', 'accent' => '--gold', 'text' => '--ink', 'background' => '--cream', 'font' => '--builder-font', 'heading_font' => '--builder-heading-font', 'radius' => '--builder-radius', 'content_width' => '--builder-content-width', 'spacing' => '--builder-spacing', 'button_padding' => '--builder-button-padding'];
        $variables = '';
        foreach ($settings['theme'] as $name => $value) {
            if ($value !== '') {
                $variables .= $names[$name].':'.$value.';';
            }
        }
        if ($variables !== '') {
            $rules = ':root{'.$variables.'}';
            if (! empty($settings['theme']['font'])) {
                $rules .= 'body{font-family:var(--builder-font)!important}';
            }
            if (! empty($settings['theme']['heading_font'])) {
                $rules .= 'body h1,body h2,body h3,body h4{font-family:var(--builder-heading-font)!important}';
            }
            if (! empty($settings['theme']['radius'])) {
                $rules .= 'body .button{border-radius:var(--builder-radius)!important}';
            }
            if (! empty($settings['theme']['button_padding'])) {
                $rules .= 'body .button{padding:var(--builder-button-padding)!important}';
            }
            if (! empty($settings['theme']['content_width'])) {
                $rules .= 'body .container,body .row-full-width-inner{max-width:var(--builder-content-width)!important}';
            }
            if (! empty($settings['theme']['spacing'])) {
                $rules .= 'body .section{padding-block:var(--builder-spacing)!important}';
            }
            $css = $rules.$css;
        }

        return $css;
    }

    public function template(string $html, string $content): string
    {
        if (! str_contains($html, 'data-builder-slot')) {
            return $html;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><div id="builder-template-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $document->getElementById('builder-template-root');
            foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
                if ($element->getAttribute('data-builder-slot') === 'content') {
                    $this->replace($element, $content);
                }
            }
            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function replace(\DOMElement $element, string $html): void
    {
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        if ($html === '') {
            return;
        }
        $temporary = new \DOMDocument('1.0', 'UTF-8');
        $temporary->loadHTML('<?xml encoding="UTF-8"><div id="builder-fragment">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $root = $temporary->getElementById('builder-fragment');
        foreach (iterator_to_array($root->childNodes) as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }
    }

    public function protect(string $html, string $baseline, bool $designer): void
    {
        if ($designer) {
            return;
        }
        $shape = function (string $value): string {
            $value = preg_replace('/\{\{.*?\}\}/s', 'BUILDERDIRECTIVE', $value);
            $previous = libxml_use_internal_errors(true);
            try {
                $document = new \DOMDocument('1.0', 'UTF-8');
                $document->loadHTML('<?xml encoding="UTF-8"><div id="builder-protection">'.$value.'</div>', LIBXML_NONET);
                foreach ($document->getElementsByTagName('*') as $element) {
                    $element->removeAttribute('data-cms-node');
                }
                $result = [];
                foreach ($document->getElementsByTagName('*') as $element) {
                    if ($element->getAttribute('data-builder-lock') === '1') {
                        $result[] = 'LOCK:'.$document->saveHTML($element);
                    }
                    $attrs = [];
                    foreach ($element->attributes as $attribute) {
                        if (! in_array($attribute->name, ['src', 'alt', 'href', 'title', 'data-cms-node'], true)) {
                            $attrs[$attribute->name] = $attribute->value;
                        }
                    }
                    ksort($attrs);
                    $result[] = $element->tagName.':'.json_encode($attrs, JSON_THROW_ON_ERROR);
                }

                return hash('sha256', implode('|', $result));
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        };
        if (! hash_equals($shape($baseline), $shape($html))) {
            throw new \InvalidArgumentException('Your role can edit content. Layouts, locked elements and styles require design permission.');
        }
    }
}
