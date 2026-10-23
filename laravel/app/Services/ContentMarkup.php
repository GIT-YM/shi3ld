<?php

namespace App\Services;

use App\Services\Builder\Schema;

class ContentMarkup
{
    public function __construct(private Schema $schema) {}

    public function validate(string $html, string $baseline, bool $render = false): string
    {
        if (strlen($html) > 524288 || ! mb_check_encoding($html, 'UTF-8') || str_contains($html, '{{') || str_contains($html, '<?')) {
            throw new \InvalidArgumentException('Use ordinary page content up to 512 KB. Template code is not supported.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><div id="shi3ld-validation-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $tags = ['div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'figure', 'figcaption', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'strong', 'em', 'b', 'i', 'u', 's', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'blockquote', 'br', 'hr', 'details', 'summary', 'svg', 'path', 'g', 'title', 'button', 'address', 'time', 'table', 'thead', 'tbody', 'tr', 'td', 'th'];
            foreach ($document->getElementsByTagName('*') as $element) {
                if (! in_array($element->tagName, $tags, true)) {
                    throw new \InvalidArgumentException('The visual editor cannot save the '.$element->tagName.' element.');
                }
                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (str_starts_with($name, 'on') || in_array($name, ['data-mage-init', 'data-bind', 'srcdoc', 'srcset', 'formaction'], true)
                        || ! preg_match('/^(?:id|class|style|href|src|alt|title|target|rel|role|width|height|loading|fetchpriority|tabindex|type|open|viewbox|d|fill|stroke|stroke-width|xmlns|colspan|rowspan|scope|datetime|aria-[a-z-]+|data-[a-z0-9_-]+)$/', $name)) {
                        throw new \InvalidArgumentException('This page contains an unsupported interactive attribute.');
                    }
                    if (in_array($name, ['href', 'src'], true) && (preg_match('/[\x00-\x20]/', $value) || str_contains($value, '\\')
                        || ! preg_match('~^(?:https://|mailto:|tel:|#|/(?!/)|[a-zA-Z0-9_-]+(?:/|$))~', $value))) {
                        throw new \InvalidArgumentException('Use relative website links, HTTPS URLs, email addresses or telephone links.');
                    }
                    if ($name === 'style' && (preg_match('/url\s*\(|expression\s*\(|@import|javascript|[<>]/i', $value) || str_contains($value, '\\'))) {
                        throw new \InvalidArgumentException('Use the image picker for images and ordinary section styles.');
                    }
                    if ($name === 'data-editor-widget' && ! in_array($value, ['service-cards', 'related-services', 'area-enquiry', 'blog-articles'], true)) {
                        throw new \InvalidArgumentException('Choose an approved service widget.');
                    }
                    if (! $render && $name === 'data-cms-node') {
                        $element->removeAttribute($attribute->name);
                    }
                }
            }
            $this->schema->attributes($document);
            $root = $document->getElementById('shi3ld-validation-root');
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
}
