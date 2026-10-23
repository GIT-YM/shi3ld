<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class Seo
{
    public function __construct(private ArticleText $text, private Metadata $metadata, private ResourceConnection $database,
        private StoreManagerInterface $stores, private ServicePaths $services, private Filesystem $filesystem) {}

    public function inspect(array $payload, int $postId = 0): array
    {
        $analysis = $this->text->analyse((string) ($payload['html'] ?? ''), $postId);
        $issues = [];
        $add = function (string $field, string $message, string $level = 'warning') use (&$issues): void {
            $issues[] = compact('field', 'message', 'level');
        };
        $title = trim((string) ($payload['meta_title'] ?: $payload['title']));
        $description = trim((string) ($payload['meta_description'] ?? ''));
        if (empty($payload['meta_title'])) {
            $add('meta_title', 'The search title uses the article title. Add a separate SEO title if needed.', 'info');
        }
        if (mb_strlen($title) > 65) {
            $add('meta_title', 'The search title is long and may be truncated.');
        }
        if ($description === '') {
            $add('meta_description', 'Add a useful SEO description.');
        } elseif (mb_strlen($description) > 170) {
            $add('meta_description', 'The description is long; review its opening sentence in the preview.', 'info');
        }
        if (empty($payload['featured_image'])) {
            $add('featured_image', 'Choose a featured image.');
        } elseif (empty($payload['featured_alt'])) {
            $add('featured_alt', 'Add a description for the featured image.');
        }
        if (empty($payload['author']) && empty($payload['editorial']['author_id'])) {
            $add('author', 'Assign an author name or profile.');
        }
        $db = $this->database->getConnection();
        foreach (['meta_title' => $title, 'meta_description' => $description] as $field => $value) {
            if ($value !== '' && $db->fetchOne($db->select()->from($this->table('post'), ['post_id'])->where($field.' = ?', $value)->where('post_id != ?', $postId)->limit(1))) {
                $add($field, 'Another article uses the same '.str_replace('_', ' ', $field).'.');
            }
        }
        if ($analysis['word_count'] < 100) {
            $add('html', 'This article contains fewer than 100 words. Check that it answers the reader’s question.', 'info');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8">'.($payload['html'] ?? ''), LIBXML_NONET);
            if ($document->getElementsByTagName('h1')->length) {
                $add('html', 'The article title supplies H1. Use H2/H3 headings inside its body.');
            }
            $level = 1;
            foreach ($document->getElementsByTagName('*') as $node) {
                if (preg_match('/^h([1-6])$/', $node->tagName, $match)) {
                    if ((int) $match[1] > $level + 1) {
                        $add('html', 'Heading levels skip from H'.$level.' to H'.$match[1].'.');
                    }$level = (int) $match[1];
                }
            }
            foreach ($document->getElementsByTagName('img') as $image) {
                if (! $image->hasAttribute('alt')) {
                    $add('html', 'An inline image needs alternative text, or an explicitly empty alt for decoration.');
                }
            }
            $links = [];
            $host = (string) parse_url($this->stores->getStore()->getBaseUrl(), PHP_URL_HOST);
            foreach ($document->getElementsByTagName('a') as $link) {
                $href = $link->getAttribute('href');
                if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:mailto:|tel:|\{\{)~', $href)) {
                    continue;
                }
                $parts = parse_url($href);
                if (! $parts || (! empty($parts['host']) && $parts['host'] !== $host)) {
                    continue;
                }
                $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
                $links[rtrim($path, '/') ?: '/'] = true;
                if (count($links) >= 50) {
                    break;
                }
            }
            foreach (array_keys($links) as $path) {
                $valid = $this->internal($path);
                if ($valid === false) {
                    $add('html', 'Review this internal link: '.$path);
                } elseif ($valid === null) {
                    $add('html', 'This internal route needs a manual check: '.$path, 'info');
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $meta = $this->metadata->get(['editorial' => $payload['editorial'] ?? []]);
        if ($meta['review_at'] !== '' && $meta['review_at'] <= gmdate('Y-m-d')) {
            $add('review_at', 'This article is due for a content review.');
        }

        return ['issues' => $issues, 'preview' => ['title' => $title, 'description' => $description, 'url' => '/blog/'.($payload['slug'] ?? ''), 'social_title' => $meta['social_title'] ?: $payload['title'], 'social_description' => $meta['social_description'] ?: $description, 'social_image' => $meta['social_image'] ?: ($payload['featured_image'] ?? '')],
            'reading' => ['words' => $analysis['word_count'], 'minutes' => $analysis['minutes'], 'headings' => count($analysis['headings'])]];
    }

    private function internal(string $path): ?bool
    {
        if (str_contains(rawurldecode($path), '..') || str_contains($path, '\\')) {
            return false;
        }
        if (in_array($path, ['/', '/shop', '/about', '/account', '/blog', '/blog/rss'], true) || preg_match('~^/(?:customer|checkout|catalog)(?:/|$)~', $path)) {
            return true;
        }
        if (str_starts_with($path, '/services')) {
            $paths = $this->services->get();

            return $paths === null ? null : in_array($path, $paths, true);
        }
        if (str_starts_with($path, '/media/')) {
            return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile(substr($path, 7));
        }
        $db = $this->database->getConnection();
        $slug = ltrim($path, '/');
        if ($db->fetchOne($db->select()->from($this->database->getTableName('url_rewrite'), ['url_rewrite_id'])->where('request_path = ?', $slug)->where('store_id = ?', (int) $this->stores->getStore()->getId())->limit(1))) {
            return true;
        }
        if ($db->fetchOne($db->select()->from($this->database->getTableName('cms_page'), ['page_id'])->where('identifier = ?', $slug)->where('is_active = ?', 1)->limit(1))) {
            return true;
        }

        return str_starts_with($path, '/blog/') ? false : null;
    }

    private function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }
}
