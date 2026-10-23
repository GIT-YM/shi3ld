<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\ArticleEditor;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Content\Model\Builder\Package;
use Shi3ld\Content\Model\Editor\Documents;

class Transfer
{
    public function __construct(private Repository $posts, private Documents $documents,
        private ArticleEditor $articles, private Package $designs, private Metadata $metadata,
        private Taxonomy $taxonomy, private Translations $translations, private ResourceConnection $database,
        private StoreManagerInterface $stores) {}

    public function export(array $ids): array
    {
        $ids = $this->metadata->ids($ids);
        if (! $ids || count($ids) > 50) {
            throw new \InvalidArgumentException('Select between 1 and 50 articles for one package.');
        }
        $records = [];
        $html = '';
        $categories = [];
        $tagIds = [];
        $authorIds = [];
        foreach ($ids as $id) {
            $payload = $this->documents->get('article', $id)['payload'];
            $translations = $this->translations->all('post', $id);
            $payloads = [$payload];
            foreach ($translations as &$translation) {
                $translation['payload'] = json_decode($translation['payload'], true, 32, JSON_THROW_ON_ERROR);
                $payloads[] = $translation['payload'];
            }unset($translation);
            $records[] = ['source_id' => $id, 'payload' => $payload, 'translations' => $translations];
            foreach ($payloads as $record) {
                $meta = $this->metadata->get(['editorial' => $record['editorial'] ?? []]);
                $html .= $record['html'];
                foreach (array_filter([$record['featured_image'] ?? '', $meta['social_image']]) as $image) {
                    $html .= '<img src="/media/'.htmlspecialchars($image, ENT_QUOTES, 'UTF-8').'" alt="">';
                }
                $categories = array_merge($categories, $record['category_ids']);
                $tagIds = array_merge($tagIds, $meta['tag_ids']);
                $authorIds[] = $meta['author_id'];
            }
        }
        $allCategories = $this->posts->categories(false);
        $include = array_fill_keys(array_map('intval', $categories), true);
        do {
            $changed = false;
            foreach ($allCategories as $category) {
                $parent = (int) ($category['parent_id'] ?? 0);
                if (isset($include[(int) $category['category_id']]) && $parent && ! isset($include[$parent])) {
                    $include[$parent] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        $categoryRows = array_values(array_filter($allCategories, fn ($row) => isset($include[(int) $row['category_id']])));
        foreach ($categoryRows as &$category) {
            $category['translations'] = $this->translations->all('category', (int) $category['category_id']);
            foreach ($category['translations'] as &$translation) {
                $translation['payload'] = json_decode($translation['payload'], true, 32, JSON_THROW_ON_ERROR);
            }unset($translation);
        }unset($category);
        $authors = array_values(array_filter($this->taxonomy->all('author'), fn ($row) => in_array((int) $row['author_id'], $authorIds, true)));
        foreach ($authors as $author) {
            if ($author['image']) {
                $html .= '<img src="/media/'.htmlspecialchars($author['image'], ENT_QUOTES, 'UTF-8').'" alt="">';
            }
        }
        $tags = array_values(array_filter($this->taxonomy->all('tag'), fn ($row) => in_array((int) $row['tag_id'], $tagIds, true)));
        $package = $this->designs->export(['title' => 'Blog content package', 'html' => $html, 'builder' => [], 'blog_records' => $records, 'authors' => $authors, 'categories' => $categoryRows, 'tags' => $tags, 'stores' => $this->translations->stores()]);
        $package['payload']['html'] = '<section></section>';
        $package['format'] = 'shi3ld-blog';
        $encoded = json_encode($package, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 20000000) {
            throw new \InvalidArgumentException('Export fewer articles; a content package is limited to 20 MB.');
        }

        return $package;
    }

    public function import(string $json, string $actor): array
    {
        if (strlen($json) > 20000000) {
            throw new \InvalidArgumentException('Choose a blog package up to 20 MB.');
        }$package = json_decode($json, true, 48, JSON_THROW_ON_ERROR);
        if (! is_array($package) || ($package['format'] ?? '') !== 'shi3ld-blog' || ($package['version'] ?? 0) !== 1 || ! is_array($package['payload']['blog_records'] ?? null) || count($package['payload']['blog_records']) > 50) {
            throw new \InvalidArgumentException('Choose a supported Shi3ld blog package.');
        }
        foreach (['authors' => 100, 'categories' => 100, 'tags' => 100, 'blog_records' => 50, 'stores' => 100] as $name => $maximum) {
            $items = $package['payload'][$name] ?? [];
            if (! is_array($items) || count($items) > $maximum) {
                throw new \InvalidArgumentException('The blog package contains an invalid collection.');
            }
            $seen = [];
            $idKey = ['authors' => 'author_id', 'categories' => 'category_id', 'tags' => 'tag_id', 'blog_records' => 'source_id', 'stores' => 'id'][$name];
            foreach ($items as $item) {
                if (! is_array($item) || ! is_scalar($item[$idKey] ?? null) || ! ctype_digit((string) $item[$idKey]) || (int) $item[$idKey] < 1 || isset($seen[(int) $item[$idKey]])) {
                    throw new \InvalidArgumentException('The blog package contains invalid or duplicate record identifiers.');
                }$seen[(int) $item[$idKey]] = true;
                if ($name === 'blog_records' && (! is_array($item['payload'] ?? null) || ! is_string($item['payload']['html'] ?? null))) {
                    throw new \InvalidArgumentException('The package contains an invalid article.');
                }
                if (isset($item['translations'])) {
                    if (! is_array($item['translations']) || count($item['translations']) > 100) {
                        throw new \InvalidArgumentException('The package contains invalid translations.');
                    }foreach ($item['translations'] as $translation) {
                        if (! is_array($translation) || ! is_array($translation['payload'] ?? null) || ! is_scalar($translation['store_id'] ?? null) || ! ctype_digit((string) $translation['store_id'])) {
                            throw new \InvalidArgumentException('The package contains an invalid translation.');
                        }
                    }
                }
            }
        }
        $normalise = function (mixed $value, ?string $name = null) use (&$normalise): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = $normalise($item, is_string($key) ? $key : null);
                }

                return $value;
            }
            if (! is_string($value)) {
                return $value;
            }
            if ($name === 'html') {
                return preg_replace_callback('~\{\{media\s+url\s*=\s*[\x22\x27]?([^\x22\x27}]*?)[\x22\x27]?\s*\}\}~', fn ($m) => '/media/'.ltrim(trim($m[1]), '/'), $value);
            }
            if (in_array($name, ['featured_image', 'social_image', 'image'], true) && str_starts_with($value, 'wysiwyg/')) {
                return '/media/'.$value;
            }

            return $value;
        };
        $package['payload'] = $normalise($package['payload']);
        $package['format'] = 'shi3ld-builder';
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            $data = $this->designs->import(json_encode($package, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), '<section></section>');
            $maps = ['author' => [], 'tag' => [], 'category' => [], 'post' => []];
            $newCategories = [];
            $warnings = [];
            foreach (['author' => 'authors', 'tag' => 'tags'] as $type => $field) {
                foreach ($data[$field] ?? [] as $row) {
                    $idKey = $type.'_id';
                    $existing = null;
                    foreach ($this->taxonomy->all($type) as $item) {
                        if ($item['slug'] === $row['slug']) {
                            $existing = $item;
                            break;
                        }
                    }
                    if ($existing) {
                        $maps[$type][(int) $row[$idKey]] = (int) $existing[$idKey];

                        continue;
                    }
                    if ($type === 'author') {
                        $row['image'] = preg_replace('~^/media/~', '', (string) $row['image']);
                    }$row['slug'] = '';
                    $id = $this->taxonomy->save($type, 0, $row);
                    $maps[$type][(int) $row[$idKey]] = $id;
                }
            }
            foreach ($data['categories'] ?? [] as $row) {
                $existing = null;
                foreach ($this->posts->categories(false) as $item) {
                    if ($item['slug'] === $row['slug']) {
                        $existing = $item;
                        break;
                    }
                }
                if ($existing) {
                    $maps['category'][(int) $row['category_id']] = (int) $existing['category_id'];

                    continue;
                }
                $original = (int) $row['category_id'];
                $row['slug'] = '';
                $row['parent_id'] = 0;
                $id = $this->posts->saveCategory(0, $row);
                $maps['category'][$original] = $id;
                $newCategories[$original] = $id;
            }
            foreach ($data['categories'] ?? [] as $row) {
                if (isset($newCategories[(int) $row['category_id']]) && ! empty($row['parent_id'])) {
                    $id = $newCategories[(int) $row['category_id']];
                    $target = $this->posts->category($id);
                    $target['parent_id'] = $maps['category'][(int) $row['parent_id']] ?? 0;
                    $target['fingerprint'] = $this->posts->fingerprint($this->posts->category($id));
                    $this->posts->saveCategory($id, $target);
                }
            }
            foreach ($data['blog_records'] as $record) {
                $sourceSlug = $this->metadata->text($record['payload']['slug'] ?? '', 180);
                $id = $this->posts->create($this->metadata->text($record['payload']['title'] ?? '', 255), $actor, $sourceSlug, true);
                $maps['post'][(int) $record['source_id']] = $id;
                if ($sourceSlug !== '' && $this->posts->post($id)['slug'] !== $sourceSlug) {
                    $warnings[] = 'An imported article URL key was renamed to avoid a collision. Review its URL before publication.';
                }
            }
            $prepare = function (array $payload, int $id) use ($maps, &$warnings): array {
                $payload['slug'] = $this->posts->post($id)['slug'];
                $payload['featured_image'] = preg_replace('~^/media/~', '', (string) ($payload['featured_image'] ?? ''));
                $payload['category_ids'] = array_values(array_filter(array_map(fn ($old) => $maps['category'][(int) $old] ?? null, $payload['category_ids'] ?? [])));
                $meta = $this->metadata->get(['editorial' => $payload['editorial'] ?? []]);
                $meta['author_id'] = $maps['author'][(int) $meta['author_id']] ?? 0;
                $meta['assigned_to'] = 0;
                $meta['canonical'] = '';
                $meta['retire_at'] = '';
                $meta['social_image'] = preg_replace('~^/media/~', '', $meta['social_image']);
                foreach (['tag_ids' => 'tag', 'pinned_ids' => 'post', 'excluded_ids' => 'post'] as $key => $type) {
                    if ($type === 'post' && array_diff($meta[$key], array_keys($maps['post']))) {
                        $warnings[] = 'Related-article selections outside this package were omitted. Review related articles before publication.';
                    }
                    $meta[$key] = array_values(array_filter(array_map(fn ($old) => $maps[$type][(int) $old] ?? null, $meta[$key])));
                }$payload['editorial'] = $meta;

                return $payload;
            };
            $storeMap = [];
            foreach ($data['stores'] ?? [] as $source) {
                foreach ($this->translations->stores() as $target) {
                    if ($source['code'] === $target['code'] && ! $target['default']) {
                        $storeMap[(int) $source['id']] = (int) $target['id'];
                    }
                }
            }
            foreach ($data['categories'] ?? [] as $category) {
                $categoryId = $maps['category'][(int) $category['category_id']];
                foreach ($category['translations'] ?? [] as $source) {
                    if (! isset($storeMap[(int) $source['store_id']])) {
                        $warnings[] = 'A category translation was skipped because its store view is not configured.';

                        continue;
                    }
                    $exists = false;
                    foreach ($this->translations->all('category', $categoryId) as $row) {
                        if ((int) $row['store_id'] === $storeMap[(int) $source['store_id']]) {
                            $exists = true;
                            break;
                        }
                    }
                    if ($exists) {
                        continue;
                    }$translationId = $this->translations->create('category', $categoryId, $storeMap[(int) $source['store_id']]);
                    $row = $this->translations->row($translationId);
                    $translated = $source['payload'];
                    $state = $this->documents->get('cat_locale', $translationId);
                    $this->documents->save('cat_locale', $translationId, $translated, 0, $actor, 'draft', $state['live_hash']);
                }
            }
            foreach ($data['blog_records'] as $record) {
                $id = $maps['post'][(int) $record['source_id']];
                $payload = $prepare($record['payload'], $id);
                $state = $this->documents->get('article', $id);
                $this->documents->save('article', $id, $payload, 0, $actor, 'draft', $state['live_hash']);
                foreach ($record['translations'] ?? [] as $source) {
                    if (! isset($storeMap[(int) $source['store_id']])) {
                        $warnings[] = 'A translation was skipped because its store view is not configured in this environment.';

                        continue;
                    }
                    $translationId = $this->translations->create('post', $id, $storeMap[(int) $source['store_id']]);
                    $translated = $prepare($source['payload'], $id);
                    $state = $this->documents->get('blog_locale', $translationId);
                    $this->documents->save('blog_locale', $translationId, $translated, 0, $actor, 'draft', $state['live_hash']);
                }
            }
            $db->commit();

            return ['imported' => count($maps['post']), 'warnings' => array_values(array_unique($warnings)), 'message' => 'Articles and linked designs were imported as drafts. Review them before publication.'];
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
