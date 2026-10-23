<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Framework\Exception\LocalizedException;

class Markup
{
    public function __construct(private array $directiveValidators = [], private ?\Shi3ld\Content\Model\Builder\Schema $builderSchema = null) {}

    public function validate(string $html, string $baseline, bool $render = false): string
    {
        if (strlen($html) > 524288 || !mb_check_encoding($html, 'UTF-8')) {
            throw new LocalizedException(__('Page content must be valid text and no larger than 512 KB.'));
        }
        preg_match_all('/\{\{.*?\}\}/s', html_entity_decode($baseline, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $allowed);
        preg_match_all('/\{\{.*?\}\}/s', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $directives);
        foreach ($directives[0] as $directive) {
            if (in_array($directive, $allowed[0], true)) {
                continue;
            }
            foreach ($this->directiveValidators as $validator) {
                if ($validator->allows($directive)) {
                    continue 2;
                }
            }
            if (!preg_match('~^\{\{(?:store|media)\s+url\s*=\s*[\x22\x27]?[a-zA-Z0-9_./#?=&%+ -]*[\x22\x27]?\s*\}\}$~D', $directive)
                || str_contains($directive, '..') || str_contains($directive, '//')) {
                throw new LocalizedException(__('This content includes an unsupported template directive. Use the approved section library.'));
            }
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $tokens = [];
            $masked = preg_replace_callback('/\{\{.*?\}\}/s', function ($match) use (&$tokens): string {
                $token = 'SHI3LDCMSDIRECTIVE'.count($tokens).'TOKEN';
                $tokens[$token] = $match[0];
                return $token;
            }, $html);
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><div id="shi3ld-validation-root">'.$masked.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $tags = ['div','section','article','header','footer','nav','aside','figure','figcaption','span','p','h1','h2','h3','h4','h5','h6','a','img','strong','em','b','i','u','s','ul','ol','li','blockquote','br','hr','details','summary','svg','path','g','title','style','button','dl','dt','dd','address','time','table','thead','tbody','tr','td','th','code','pre','small','sup','sub'];
            preg_match_all('~<style\b[^>]*>.*?</style>~is', $baseline, $styles);
            foreach ($document->getElementsByTagName('*') as $element) {
                if (!in_array($element->tagName, $tags, true)) {
                    throw new LocalizedException(__('The visual editor cannot save the <%1> element.', $element->tagName));
                }
                if ($element->tagName === 'style' && !in_array(strtr($document->saveHTML($element), $tokens), $styles[0], true)) {
                    throw new LocalizedException(__('Keep existing Page Builder style sheets unchanged. Use the section settings for new styles.'));
                }
                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (str_starts_with($name, 'on') || in_array($name, ['data-mage-init','data-bind','srcdoc','formaction','srcset'], true)) {
                        throw new LocalizedException(__('This content includes an unsupported interactive attribute.'));
                    }
                    if (!preg_match('/^(?:id|class|style|href|src|alt|title|target|rel|role|width|height|loading|fetchpriority|tabindex|type|open|viewbox|d|fill|stroke|stroke-width|xmlns|colspan|rowspan|scope|datetime|aria-[a-z-]+|data-[a-z0-9_-]+)$/', $name)) {
                        throw new LocalizedException(__('The visual editor cannot save the %1 attribute.', $name));
                    }
                    if (in_array($name, ['href','src'], true) && !$this->isSafeUrl($value)) {
                        throw new LocalizedException(__('Use a relative website link, HTTPS URL, email address or telephone link.'));
                    }
                    if ($name === 'style' && (preg_match('/(?:url\s*\(|expression\s*\(|@import|javascript|[<>])/i', $value) || str_contains($value, '\\'))) {
                        throw new LocalizedException(__('Use the image picker for background images.'));
                    }
                    if (!$render && $name === 'data-cms-node') {
                        $element->removeAttribute($attribute->name);
                    }
                }
            }
            $this->builderSchema?->attributes($document);
            $root = $document->getElementById('shi3ld-validation-root');
            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }
            return strtr($result, $tokens);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function isSafeUrl(string $value): bool
    {
        $value = trim($value);
        $literal = preg_replace('/\{\{.*?\}\}/s', '', $value);
        return !preg_match('/[\x00-\x20]/', $literal) && !str_contains($literal, '\\')
            && ($value === '' || str_starts_with($value, '{{')
                || preg_match('~^(?:https://|mailto:|tel:|#|/(?!/)|[a-zA-Z0-9_-]+(?:/|$))~', $value));
    }
}
