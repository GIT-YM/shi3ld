<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;

class Metadata
{
    public function __construct(private ResourceConnection $database, private Filesystem $filesystem) {}

    public function defaults(): array
    {
        return ['author_id' => 0, 'tag_ids' => [], 'pinned_ids' => [], 'excluded_ids' => [], 'product_skus' => [], 'services' => [],
            'social_title' => '', 'social_description' => '', 'social_image' => '', 'canonical' => '', 'image_caption' => '', 'image_credit' => '',
            'assigned_to' => 0, 'due_at' => '', 'review_at' => '', 'retire_at' => '', 'show_toc' => true, 'show_progress' => true, 'allow_comments' => false];
    }

    public function get(array $post): array
    {
        $value = $post['editorial'] ?? null;

        return array_replace($this->defaults(), is_array($value) ? $value : ($value ? json_decode($value, true, 32, JSON_THROW_ON_ERROR) : []));
    }

    public function validate(array $input): array
    {
        $result = $this->defaults();
        foreach (['social_title' => 255, 'social_description' => 1000, 'canonical' => 1000, 'social_image' => 512, 'image_caption' => 500, 'image_credit' => 300] as $name => $limit) {
            $result[$name] = $this->text($input[$name] ?? '', $limit);
        }
        $result['social_image'] = $this->image($result['social_image']);
        if ($result['canonical'] !== '') {
            $url = $result['canonical'];
            $parts = parse_url($url);
            if (preg_match('/[\x00-\x20\\\\<>\x22\x27]/', $url) || ! $parts || isset($parts['user']) || isset($parts['fragment'])
                || ! (str_starts_with($url, '/') && ! str_starts_with($url, '//') || (($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])))) {
                throw new \InvalidArgumentException('Use an absolute HTTPS canonical URL or a relative website path.');
            }
        }
        foreach (['author_id', 'assigned_to'] as $name) {
            $result[$name] = max(0, (int) ($input[$name] ?? 0));
        }
        foreach (['tag_ids', 'pinned_ids', 'excluded_ids'] as $name) {
            $result[$name] = $this->ids($input[$name] ?? []);
        }
        $db = $this->database->getConnection();
        if ($result['author_id'] && ! $db->fetchOne($db->select()->from($this->table('author'), ['author_id'])->where('author_id = ?', $result['author_id']))) {
            throw new \InvalidArgumentException('Choose an existing author profile.');
        }
        if ($result['assigned_to'] && ! $db->fetchOne($db->select()->from($this->database->getTableName('admin_user'), ['user_id'])->where('user_id = ?', $result['assigned_to'])->where('is_active = ?', 1))) {
            throw new \InvalidArgumentException('Choose an active editor.');
        }
        if ($result['tag_ids']) {
            $valid = array_map('intval', $db->fetchCol($db->select()->from($this->table('tag'), ['tag_id'])->where('tag_id IN (?)', $result['tag_ids'])));
            if (array_diff($result['tag_ids'], $valid)) {
                throw new \InvalidArgumentException('One of the selected tags no longer exists.');
            }
        }
        foreach (['due_at', 'review_at', 'retire_at'] as $name) {
            $result[$name] = $this->date($input[$name] ?? '');
        }
        foreach (['show_toc', 'show_progress', 'allow_comments'] as $name) {
            $result[$name] = array_key_exists($name, $input) ? ! empty($input[$name]) : $result[$name];
        }
        if (! is_array($input['product_skus'] ?? []) || count($input['product_skus'] ?? []) > 24) {
            throw new \InvalidArgumentException('Choose up to 24 product SKUs.');
        }
        $result['product_skus'] = array_values(array_unique(array_filter(array_map(fn ($value) => $this->text($value, 64), $input['product_skus'] ?? []))));
        if (! is_array($input['services'] ?? []) || count($input['services'] ?? []) > 12) {
            throw new \InvalidArgumentException('Choose up to 12 service links.');
        }
        foreach ($input['services'] ?? [] as $service) {
            $url = $this->text($service['url'] ?? '', 300);
            $label = $this->text($service['label'] ?? '', 150);
            if ($label === '' || ! preg_match('~^/services(?:/[a-z0-9-]+)*$~D', $url) || preg_match('~^/services/(?:staff|builder|editor)(?:/|$)~', $url)) {
                throw new \InvalidArgumentException('Service links need a label and a public /services path.');
            }
            $result['services'][] = ['label' => $label, 'url' => $url];
        }

        return $result;
    }

    public function authorName(array $post): string
    {
        $id = $this->get($post)['author_id'];
        $db = $this->database->getConnection();
        $name = $id ? $db->fetchOne($db->select()->from($this->table('author'), ['name'])->where('author_id = ?', $id)->where('is_active = ?', 1)) : false;

        return $name !== false ? (string) $name : (string) ($post['author'] ?? '');
    }

    public function image(string $path): string
    {
        if ($path !== '' && (! preg_match('~^wysiwyg/[a-zA-Z0-9_./-]+\.(?:png|jpe?g|webp|gif)$~iD', $path) || str_contains($path, '..') || ! $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile($path))) {
            throw new \InvalidArgumentException('Choose an existing Magento gallery image.');
        }

        return $path;
    }

    public function ids(mixed $input): array
    {
        if (! is_array($input) || count($input) > 100) {
            throw new \InvalidArgumentException('Choose up to 100 valid records.');
        }
        $ids = [];
        foreach ($input as $id) {
            if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id < 1) {
                throw new \InvalidArgumentException('Choose valid record identifiers.');
            }$ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    public function text(mixed $value, int $limit): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit) {
            throw new \InvalidArgumentException('A blog field contains invalid or oversized text.');
        }

        return trim(strip_tags($value));
    }

    public function date(mixed $value): string
    {
        $value = $this->text($value, 10);
        if ($value !== '' && (! ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value)) || $date->format('Y-m-d') !== $value)) {
            throw new \InvalidArgumentException('Enter a valid date in YYYY-MM-DD format.');
        }

        return $value;
    }

    private function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }
}
