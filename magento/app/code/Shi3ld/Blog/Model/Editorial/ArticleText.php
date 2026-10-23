<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

class ArticleText
{
    public function analyse(string $html, int $postId = 0): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><div id="blog-reading-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $document->getElementById('blog-reading-root');
            $xpath = new \DOMXPath($document);
            $headings = [];
            $used = [];
            foreach ($root->getElementsByTagName('*') as $element) {
                if (! in_array($element->tagName, ['h2', 'h3'], true) && $element->hasAttribute('id')) {
                    $used[$element->getAttribute('id')] = true;
                }
            }
            foreach ($xpath->query('.//h2|.//h3', $root) as $heading) {
                $label = trim(preg_replace('/\s+/u', ' ', $heading->textContent));
                if ($label === '') {
                    continue;
                }
                $id = $heading->getAttribute('id');
                if ($id === '' || isset($used[$id]) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,100}$/D', $id)) {
                    $id = 'article-'.$postId.'-section-'.(count($headings) + 1);
                }
                while (isset($used[$id])) {
                    $id .= '-next';
                }$used[$id] = true;
                $heading->setAttribute('id', $id);
                $headings[] = ['id' => $id, 'text' => $label, 'level' => (int) substr($heading->tagName, 1)];
            }
            $copy = clone $root;
            foreach (iterator_to_array($copy->getElementsByTagName('script')) as $element) {
                $element->parentNode->removeChild($element);
            }
            foreach (iterator_to_array($copy->getElementsByTagName('style')) as $element) {
                $element->parentNode->removeChild($element);
            }
            foreach (iterator_to_array($copy->getElementsByTagName('*')) as $element) {
                if (in_array($element->tagName, ['p', 'div', 'section', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'br'], true)) {
                    $element->appendChild($document->createTextNode(' '));
                }
            }
            $text = trim(preg_replace('/\s+/u', ' ', $copy->textContent));
            preg_match_all('/[\p{L}\p{N}]+(?:[’\x27-][\p{L}\p{N}]+)*/u', $text, $words);
            preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $text, $characters);
            $minutes = max(1, (int) ceil(max(count($words[0]) / 220, count($characters[0]) / 500)));
            $content = '';
            foreach ($root->childNodes as $child) {
                $content .= $document->saveHTML($child);
            }

            return ['html' => $content, 'headings' => $headings, 'word_count' => count($words[0]), 'minutes' => $minutes, 'text' => $text];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
