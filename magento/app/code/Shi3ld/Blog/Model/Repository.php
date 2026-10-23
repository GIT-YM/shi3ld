<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Shi3ld\Blog\Model\Editorial\Metadata;

/** Persistence is limited to tables declared by Shi3ld_Blog. */
class Repository
{
    public function __construct(private ResourceConnection $database, private Urls $urls, private Metadata $metadata) {}

    public function table(string $name): string
    {
        return $this->database->getTableName('shi3ld_blog_'.$name);
    }

    public function post(int $id): array
    {
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table('post'))->where('post_id = ?', $id));
        if (! $row) {
            throw new NoSuchEntityException(__('This article no longer exists.'));
        }
        $row['category_ids'] = array_map('intval', $db->fetchCol($db->select()->from($this->table('post_category'), ['category_id'])
            ->where('post_id = ?', $id)->order('category_id ASC')));

        return $row;
    }

    public function category(int $id): array
    {
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table('category'))->where('category_id = ?', $id));
        if (! $row) {
            throw new NoSuchEntityException(__('This category no longer exists.'));
        }

        return $row;
    }

    public function categories(bool $activeOnly = true): array
    {
        $db = $this->database->getConnection();
        $select = $db->select()->from($this->table('category'))->order(['position ASC', 'title ASC']);
        if ($activeOnly) {
            $select->where('is_active = ?', 1);
        }

        return $db->fetchAll($select);
    }

    public function assignedCategories(int $id): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from(['c' => $this->table('category')])
            ->joinInner(['a' => $this->table('post_category')], 'a.category_id = c.category_id', [])
            ->where('a.post_id = ?', $id)->where('c.is_active = ?', 1)->order(['c.position ASC', 'c.title ASC']));
    }

    public function listing(array $options = [], int $page = 1, int $limit = 12, bool $publishedOnly = true): array
    {
        $db = $this->database->getConnection();
        $select = $db->select()->from(['p' => $this->table('post')]);
        $localized = $publishedOnly;
        if ($localized) {
            $storeId = (int) ($options['store_id'] ?? $this->urls->storeId());
            $select->joinLeft(['tr' => $this->table('translation')], "tr.entity_type = 'post' AND tr.entity_id = p.post_id AND tr.store_id = ".$storeId.' AND tr.is_published = 1', ['translation_payload' => 'payload', 'translation_updated' => 'updated_at']);
            $select->where('p.is_published = ?', 1)->where('p.published_at <= ?', gmdate('Y-m-d H:i:s'));
        }
        if (! empty($options['categories'])) {
            $slugs = is_array($options['categories']) ? $options['categories'] : explode(',', (string) $options['categories']);
            $options['category_ids'] = $db->fetchCol($db->select()->from($this->table('category'), ['category_id'])->where('slug IN (?)', $slugs));
            if (! $options['category_ids']) {
                $options['category_ids'] = [0];
            }
        }
        if (! empty($options['category_ids'])) {
            $ids = $this->descendants(array_map('intval', $options['category_ids']), $publishedOnly);
            $sub = $db->select()->from(['a' => $this->table('post_category')], ['post_id'])->joinInner(['c' => $this->table('category')], 'c.category_id = a.category_id', [])->where('c.category_id IN (?)', $ids ?: [0]);
            if ($publishedOnly) {
                $sub->where('c.is_active = ?', 1);
            }
            $condition = 'p.post_id IN ('.$sub.')';
            if ($localized) {
                $json = $db->quote(json_encode($ids ?: [0], JSON_THROW_ON_ERROR));
                $condition = '((tr.translation_id IS NULL AND '.$condition.') OR (tr.translation_id IS NOT NULL AND JSON_OVERLAPS(JSON_EXTRACT(tr.payload,\'$.category_ids\'), CAST('.$json.' AS JSON))))';
            }
            $select->where($condition);
        }
        if (! empty($options['tag_ids'])) {
            $ids = array_values(array_filter(array_map('intval', (array) $options['tag_ids'])));
            $sub = $db->select()->from($this->table('post_tag'), ['post_id'])->where('tag_id IN (?)', $ids ?: [0]);
            $condition = 'p.post_id IN ('.$sub.')';
            if ($localized) {
                $condition = '((tr.translation_id IS NULL AND '.$condition.') OR (tr.translation_id IS NOT NULL AND JSON_OVERLAPS(JSON_EXTRACT(tr.payload,\'$.editorial.tag_ids\'),CAST('.$db->quote(json_encode($ids, JSON_THROW_ON_ERROR)).' AS JSON))))';
            }
            $select->where($condition);
        }
        if (! empty($options['updated_since'])) {
            $select->where('p.updated_at > '.$db->quote($options['updated_since']).($localized ? ' OR tr.updated_at > '.$db->quote($options['updated_since']) : ''));
        }
        if (! empty($options['author_id'])) {
            $field = $localized ? 'COALESCE(JSON_EXTRACT(tr.payload,\'$.editorial.author_id\'),p.author_id)' : 'p.author_id';
            $select->where($field.' = ?', (int) $options['author_id']);
        }
        foreach (['from' => '>=', 'to' => '<='] as $date => $operator) {
            if (! empty($options[$date])) {
                $select->where('p.published_at '.$operator.' ?', $this->metadata->date($options[$date]).($date === 'to' ? ' 23:59:59' : ' 00:00:00'));
            }
        }
        if (! empty($options['exclude'])) {
            $select->where('p.post_id NOT IN (?)', array_map('intval', is_array($options['exclude']) ? $options['exclude'] : [$options['exclude']]));
        }
        if (($options['sort'] ?? '') === 'featured') {
            $select->where(($localized ? "COALESCE(JSON_EXTRACT(tr.payload,'$.is_featured'),p.is_featured)" : 'p.is_featured').' = ?', 1);
        }
        $fields = [];
        foreach (['title' => 'title', 'excerpt' => 'excerpt', 'content' => 'html'] as $column => $property) {
            $fields[$column] = $localized ? 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tr.payload,\'$.'.$property.'\')),p.'.$column.')' : 'p.'.$column;
        }
        $term = trim((string) ($options['search'] ?? ''));
        if ($term !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($term, 0, 100));
            $like = $db->quote('%'.$escaped.'%');
            $select->where('('.implode(' OR ', array_map(fn ($field) => $field.' LIKE '.$like, $fields)).')');
        }
        if (! $publishedOnly && isset($options['status']) && in_array($options['status'], ['0', '1'], true)) {
            $select->where('p.is_published = ?', (int) $options['status']);
        }
        $manual = ($options['sort'] ?? '') === 'manual';
        $ids = [];
        if ($manual) {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($options['articles'] ?? null) ? $options['articles'] : explode(',', (string) ($options['articles'] ?? ''))))));
            $select->where('p.post_id IN (?)', $ids ?: [0]);
        }
        $count = (int) $db->fetchOne((clone $select)->reset(Select::COLUMNS)->columns(new \Zend_Db_Expr('COUNT(*)')));
        if ($manual && $ids) {
            $select->order(new \Zend_Db_Expr('FIELD(p.post_id,'.implode(',', $ids).')'));
        } elseif (($options['sort'] ?? '') === 'relevance' && $term !== '') {
            $select->order(new \Zend_Db_Expr('(CASE WHEN '.$fields['title'].' LIKE '.$like.' THEN 3 ELSE 0 END + CASE WHEN '.$fields['excerpt'].' LIKE '.$like.' THEN 2 ELSE 0 END) DESC'))->order('p.published_at DESC');
        } elseif (($options['sort'] ?? '') === 'updated') {
            $select->order(new \Zend_Db_Expr($localized ? 'GREATEST(p.updated_at,COALESCE(tr.updated_at,p.updated_at)) DESC' : 'p.updated_at DESC'));
        } else {
            $direction = ($options['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC';
            $select->order(['p.published_at '.$direction, 'p.post_id '.$direction]);
        }
        $select->limitPage(max(1, $page), max(1, min(100, $limit)));
        $rows = $db->fetchAll($select);
        foreach ($rows as &$row) {
            if (! empty($row['translation_payload'])) {
                $payload = json_decode($row['translation_payload'], true, 32, JSON_THROW_ON_ERROR);
                $row['updated_at'] = max($row['updated_at'], $row['translation_updated']);
                $row = array_replace($row, $payload);
                if (isset($payload['html'])) {
                    $row['content'] = $payload['html'];
                }
                if (isset($payload['editorial'])) {
                    $row['editorial'] = json_encode($payload['editorial'], JSON_THROW_ON_ERROR);
                    $row['author_id'] = $payload['editorial']['author_id'] ?? null;
                }
            }
            if ($publishedOnly) {
                $row['author'] = $this->metadata->authorName($row);
            }
            unset($row['translation_payload'],$row['translation_updated']);
        }unset($row);

        return ['items' => $rows, 'total' => $count];
    }

    public function descendants(array $ids, bool $activeOnly = true): array
    {
        $result = array_values(array_unique(array_map('intval', $ids)));
        $categories = $this->categories($activeOnly);
        do {
            $changed = false;
            foreach ($categories as $category) {
                if (in_array((int) ($category['parent_id'] ?? 0), $result, true) && ! in_array((int) $category['category_id'], $result, true)) {
                    $result[] = (int) $category['category_id'];
                    $changed = true;
                }
            }
        } while ($changed);

        return $result;
    }

    public function create(string $title, string $author, string $requestedSlug = '', bool $allowRename = false): int
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 255) {
            throw new LocalizedException(__('Enter an article title up to 255 characters.'));
        }
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            try {
                $slug = $this->urls->slug('post', $requestedSlug, $title);
            } catch (LocalizedException $exception) {
                if (! $allowRename) {
                    throw $exception;
                }$slug = $this->urls->slug('post', '', $title);
            }
            $db->insert($this->table('post'), ['title' => $title, 'slug' => $slug, 'author' => mb_substr($author, 0, 150),
                'content' => '<section class="blog-copy"><h2>Your introduction</h2><p>Write your article here. Use Add to insert images, sections and helpful links.</p></section>']);
            $id = (int) $db->lastInsertId($this->table('post'));
            $this->urls->sync('post', $id, $slug, true);
            $db->commit();

            return $id;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function publish(int $id, array $payload): void
    {
        $db = $this->database->getConnection();
        $post = $this->post($id);
        $slug = $this->urls->slug('post', $payload['slug'], $payload['title'], $id);
        $editorial = isset($payload['editorial']) ? $this->metadata->validate($payload['editorial']) : null;
        if (! empty($editorial['author_id'])) {
            $payload['author'] = (string) $db->fetchOne($db->select()->from($this->table('author'), ['name'])->where('author_id = ?', $editorial['author_id']));
        }
        $db->update($this->table('post'), [
            'title' => $payload['title'], 'content' => $payload['html'], 'slug' => $slug,
            'excerpt' => $payload['excerpt'], 'featured_image' => $payload['featured_image'], 'featured_alt' => $payload['featured_alt'],
            'author' => $payload['author'], 'meta_title' => $payload['meta_title'], 'meta_description' => $payload['meta_description'],
            'is_featured' => $payload['is_featured'], 'is_published' => 1,
            'author_id' => ($editorial['author_id'] ?? 0) ?: null, 'editorial' => $editorial !== null ? json_encode($editorial, JSON_THROW_ON_ERROR) : null,
            'published_at' => $post['published_at'] ?: gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['post_id = ?' => $id]);
        $db->delete($this->table('post_category'), ['post_id = ?' => $id]);
        foreach ($payload['category_ids'] as $categoryId) {
            $db->insert($this->table('post_category'), ['post_id' => $id, 'category_id' => $categoryId]);
        }
        $db->delete($this->table('post_tag'), ['post_id = ?' => $id]);
        foreach ($editorial['tag_ids'] ?? [] as $tag) {
            $db->insert($this->table('post_tag'), ['post_id' => $id, 'tag_id' => $tag]);
        }
        $this->urls->sync('post', $id, $slug, true);
    }

    public function unpublish(int $id, string $fingerprint): void
    {
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            $db->fetchOne($db->select()->from($this->table('post'), ['post_id'])->where('post_id = ?', $id)->forUpdate(true));
            $post = $this->post($id);
            if (! hash_equals($this->fingerprint($post), $fingerprint)) {
                throw new LocalizedException(__('This article changed. Reload the list before unpublishing.'));
            }
            $db->update($this->table('post'), ['is_published' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')], ['post_id = ?' => $id]);
            $db->commit();
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function fingerprint(array $row): string
    {
        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function saveCategory(int $id, array $input): int
    {
        $data = [];
        foreach (['title' => 255, 'description' => 5000, 'meta_title' => 255, 'meta_description' => 1000, 'slug' => 180] as $key => $length) {
            if (! is_string($input[$key] ?? '') || mb_strlen($input[$key] ?? '') > $length) {
                throw new LocalizedException(__('Enter a valid %1.', $key));
            }
            $data[$key] = trim($input[$key] ?? '');
        }
        if ($data['title'] === '') {
            throw new LocalizedException(__('Enter a category title.'));
        }
        $data['is_active'] = empty($input['is_active']) ? 0 : 1;
        $data['position'] = max(0, min(10000, (int) ($input['position'] ?? 0)));
        $parent = max(0, (int) ($input['parent_id'] ?? 0));
        $visited = [$id];
        $current = $parent;
        while ($current) {
            if (in_array($current, $visited, true)) {
                throw new LocalizedException(__('Category parents cannot contain a cycle.'));
            }$visited[] = $current;
            $current = (int) ($this->category($current)['parent_id'] ?? 0);
        }
        $data['parent_id'] = $parent ?: null;
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            if ($id) {
                $db->fetchOne($db->select()->from($this->table('category'), ['category_id'])->where('category_id = ?', $id)->forUpdate(true));
                $old = $this->category($id);
                if (! hash_equals($this->fingerprint($old), (string) ($input['fingerprint'] ?? ''))) {
                    throw new LocalizedException(__('This category changed. Reload it before saving.'));
                }
                // A blank field on an existing category preserves its published URL.
                $data['slug'] = $data['slug'] ?: $old['slug'];
            }
            $data['slug'] = $this->urls->slug('category', $data['slug'], $data['title'], $id);
            if ($id) {
                $db->update($this->table('category'), $data, ['category_id = ?' => $id]);
            } else {
                $db->insert($this->table('category'), $data);
                $id = (int) $db->lastInsertId($this->table('category'));
            }
            $this->urls->sync('category', $id, $data['slug'], true);
            $db->commit();

            return $id;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
