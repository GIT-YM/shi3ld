<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\User\Model\User;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Lifecycle;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Content\Model\Builder\Workflow;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Model\Editor\Images;

class Studio
{
    public function __construct(private Repository $posts, private Documents $documents,
        private Metadata $metadata, private Taxonomy $taxonomy, private Translations $translations, private Notes $notes, private Presence $presence,
        private Seo $seo, private Transfer $transfer, private Settings $settings, private Engagement $engagement, private Delivery $delivery,
        private Workflow $workflow, private ResourceConnection $database,
        private UrlInterface $backend, private CollectionFactory $products,
        private Images $images, private Lifecycle $lifecycle, private LoggerInterface $logger) {}

    public function overview(array $input = []): array
    {
        $search = $this->metadata->text($input['search'] ?? '', 100);
        $page = max(1, min(100000, (int) ($input['page'] ?? 1)));
        $options = ['search' => $search, 'status' => is_string($input['status'] ?? null) ? $input['status'] : ''];
        if (! empty($input['category'])) {
            $options['category_ids'] = [(int) $input['category']];
        }
        $listing = $this->posts->listing($options, $page, 50, false);
        $articles = [];
        foreach ($listing['items'] as $post) {
            $id = (int) $post['post_id'];
            $state = $this->documents->get('article', $id);
            $payload = $state['payload'];
            $articles[] = ['id' => $id, 'title' => $payload['title'], 'slug' => $post['slug'], 'published' => (bool) $post['is_published'], 'has_draft' => (bool) $state['has_draft'],
                'version' => $state['version'], 'live_hash' => $state['live_hash'], 'updated_at' => $post['updated_at'], 'editorial' => $this->metadata->get(['editorial' => $payload['editorial'] ?? []]),
                'workflow' => $this->workflow->get('article', $id), 'url' => $this->editorUrl('article', $id)];
        }
        $authors = array_map(fn ($row) => $row + ['fingerprint' => $this->taxonomy->fingerprint($row)], $this->taxonomy->all('author'));
        $tags = array_map(fn ($row) => $row + ['fingerprint' => $this->taxonomy->fingerprint($row)], $this->taxonomy->all('tag'));
        $translations = [];
        foreach ($this->translations->all() as $row) {
            $type = $row['entity_type'] === 'post' ? 'blog_locale' : 'cat_locale';
            $state = $this->documents->get($type, (int) $row['translation_id']);
            $translations[] = ['id' => (int) $row['translation_id'], 'entity_type' => $row['entity_type'], 'entity_id' => (int) $row['entity_id'], 'store_id' => (int) $row['store_id'], 'title' => $state['payload']['title'], 'published' => (bool) $row['is_published'], 'url' => $row['entity_type'] === 'post' ? $this->editorUrl($type, (int) $row['translation_id']) : null];
        }

        return ['articles' => $articles, 'total' => $listing['total'], 'page' => $page, 'authors' => $authors, 'tags' => $tags, 'categories' => $this->posts->categories(false),
            'staff' => $this->staff(), 'stores' => $this->translations->stores(), 'translations' => $translations, 'settings' => $this->settings->get()];
    }

    public function run(string $intent, array $input, User $actor, bool $publisher, bool $designer, ?array $file = null): array
    {
        if ($intent === 'overview') {
            return $this->overview($input);
        }
        if ($intent === 'calendar') {
            $month = $this->metadata->text($input['month'] ?? gmdate('Y-m'), 7);
            $start = $this->metadata->date($month.'-01');
            $end = (new \DateTimeImmutable($start.' UTC'))->modify('first day of next month')->format('Y-m-d');
            $db = $this->database->getConnection();
            $source = $db->select()->from(['p' => $this->database->getTableName('shi3ld_blog_post')], ['post_id'])
                ->joinLeft(['d' => $this->database->getTableName('shi3ld_cms_document')], "d.entity_type = 'article' AND d.entity_id = p.post_id", [])
                ->joinLeft(['w' => $this->database->getTableName('shi3ld_builder_workflow')], "w.entity_type = 'article' AND w.entity_id = p.post_id", []);
            $conditions = [];
            foreach (['due_at', 'review_at', 'retire_at'] as $field) {
                $conditions[] = 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.payload,\'$.editorial.'.$field.'\')),JSON_UNQUOTE(JSON_EXTRACT(p.editorial,\'$.'.$field.'\'))) LIKE '.$db->quote($month.'-%');
            }
            $conditions[] = "(w.state = 'scheduled' AND w.scheduled_at >= ".$db->quote($start).' AND w.scheduled_at < '.$db->quote($end).')';
            $source->where(implode(' OR ', $conditions))->limit(500);
            $articles = [];
            foreach ($db->fetchCol($source) as $id) {
                $state = $this->documents->get('article', (int) $id);
                $articles[] = ['id' => (int) $id, 'title' => $state['payload']['title'], 'version' => $state['version'], 'live_hash' => $state['live_hash'], 'editorial' => $this->metadata->get(['editorial' => $state['payload']['editorial'] ?? []]), 'workflow' => $this->workflow->get('article', (int) $id), 'url' => $this->editorUrl('article', (int) $id)];
            }

            return ['articles' => $articles, 'month' => $month, 'limit' => 500];
        }
        if (in_array($intent, ['reschedule', 'set_date'], true)) {
            $id = (int) ($input['id'] ?? 0);
            $state = $this->documents->get('article', $id);
            if ($state['version'] !== (int) ($input['version'] ?? -1) || ! hash_equals($state['live_hash'], (string) ($input['live_hash'] ?? ''))) {
                throw new \InvalidArgumentException('This article changed. Refresh the calendar before saving.');
            }
            if ($intent === 'reschedule') {
                if (! $publisher) {
                    throw new \InvalidArgumentException('Rescheduling requires publication permission.');
                }

                return ['workflow' => $this->workflow->request('article', $id, $state['version'], $actor, (string) ($input['scheduled_at'] ?? ''))];
            }
            $field = $input['field'] ?? '';
            if (! in_array($field, ['due_at', 'review_at', 'retire_at'], true)) {
                throw new \InvalidArgumentException('Choose a valid editorial date.');
            }
            $payload = $state['payload'];
            $payload['editorial'] = $this->metadata->get(['editorial' => $payload['editorial'] ?? []]);
            $payload['editorial'][$field] = $this->metadata->date($input['date'] ?? '');
            $this->documents->save('article', $id, $payload, $state['version'], (string) $actor->getUserName(), 'draft', $state['live_hash']);

            return ['message' => 'Date saved in the draft. Publish retirement changes to activate them.'];
        }
        if (in_array($intent, ['editor', 'heartbeat', 'release', 'note', 'seo'], true)) {
            $type = $input['entity_type'] ?? 'article';
            if (! in_array($type, ['article', 'blog_locale'], true)) {
                throw new \InvalidArgumentException('Choose an article or article translation.');
            }$id = (int) ($input['id'] ?? 0);
            $state = $this->documents->get($type, $id);
            $baseId = $type === 'article' ? $id : (int) $this->translations->row($id)['entity_id'];
            if ($intent === 'release') {
                $this->presence->release($id, (int) $actor->getId(), $type);

                return ['ok' => true];
            }
            if ($intent === 'note') {
                $this->notes->add($baseId, (string) $actor->getUserName(), $this->metadata->text($input['body'] ?? '', 3000));

                return ['notes' => $this->notes->all($baseId)];
            }
            if ($intent === 'seo') {
                $json = $input['payload'] ?? null;
                if ($json !== null && (! is_string($json) || strlen($json) > 650000)) {
                    throw new \InvalidArgumentException('Choose a valid article draft.');
                }$payload = $json === null ? $state['payload'] : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \InvalidArgumentException('Choose a valid article draft.');
                }$payload = $this->documents->validate($payload, $state['payload']['html'], true, $type);

                return ['report' => $this->seo->inspect($payload, $baseId)];
            }
            $takeover = ! empty($input['takeover']);
            if ($takeover && ! $publisher) {
                throw new \InvalidArgumentException('Taking over an editing lease requires publication permission.');
            }
            $lease = $this->presence->heartbeat($id, $actor, $takeover, $type);
            if ($intent === 'heartbeat') {
                return ['presence' => $lease, 'version' => $state['version']];
            }

            return ['presence' => $lease, 'base_id' => $baseId, 'authors' => $this->taxonomy->all('author'), 'tags' => $this->taxonomy->all('tag'), 'staff' => $this->staff(),
                'notes' => $this->notes->all($baseId), 'articles' => $this->options(), 'version' => $state['version']];
        }
        if ($intent === 'products') {
            $term = $this->metadata->text($input['search'] ?? '', 100);
            $collection = $this->products->create()->addAttributeToSelect(['name', 'sku'])->addAttributeToFilter('status', 1)->setPageSize(12);
            if ($term !== '') {
                $collection->addAttributeToFilter([['attribute' => 'name', 'like' => '%'.addcslashes($term, '%_\\').'%'], ['attribute' => 'sku', 'like' => '%'.addcslashes($term, '%_\\').'%']]);
            }
            $items = [];
            foreach ($collection as $product) {
                $items[] = ['sku' => $product->getSku(), 'name' => $product->getName()];
            }

            return ['products' => $items];
        }
        if ($intent === 'create_translation') {
            $type = $input['kind'] ?? '';
            if (! in_array($type, ['post', 'category'], true)) {
                throw new \InvalidArgumentException('Choose an article or category.');
            }
            $id = $this->translations->create($type, (int) ($input['id'] ?? 0), (int) ($input['store_id'] ?? 0));

            return ['id' => $id, 'url' => $type === 'post' ? $this->editorUrl('blog_locale', $id) : null];
        }
        if ($intent === 'category_translation') {
            $id = (int) ($input['id'] ?? 0);
            $row = $this->translations->row($id);
            if ($row['entity_type'] !== 'category') {
                throw new \InvalidArgumentException('Choose a category translation.');
            }

            return ['state' => $this->documents->get('cat_locale', $id)];
        }
        if ($intent === 'save_category_translation') {
            if (! empty($input['publish']) && ! $publisher) {
                throw new \InvalidArgumentException('Publishing requires publication permission.');
            }
            $id = (int) ($input['id'] ?? 0);
            $row = $this->translations->row($id);
            if ($row['entity_type'] !== 'category') {
                throw new \InvalidArgumentException('Choose a category translation.');
            }
            $state = $this->documents->get('cat_locale', $id);
            $payload = $state['payload'];
            foreach (['title' => 255, 'slug' => 180, 'description' => 5000, 'meta_title' => 255, 'meta_description' => 1000] as $key => $limit) {
                $payload[$key] = $this->metadata->text($input[$key] ?? '', $limit);
            }
            $payload['html'] = '<section><p>'.htmlspecialchars($payload['description'], ENT_QUOTES, 'UTF-8').'</p></section>';
            $state = $this->documents->save('cat_locale', $id, $payload, (int) ($input['version'] ?? -1), (string) $actor->getUserName(), 'draft', (string) ($input['live_hash'] ?? ''));
            if (! empty($input['publish'])) {
                if (! $publisher) {
                    throw new \InvalidArgumentException('Publishing requires publication permission.');
                }$state = $this->documents->publish('cat_locale', $id, $state['version'], (string) $actor->getUserName());
            }

            return ['state' => $state];
        }
        if ($intent === 'bulk') {
            $ids = $this->metadata->ids($input['ids'] ?? []);
            if (! $ids || count($ids) > 50) {
                throw new \InvalidArgumentException('Choose between 1 and 50 articles.');
            }$action = $input['action'] ?? '';
            if (! in_array($action, ['publish', 'unpublish', 'assign', 'tags'], true)) {
                throw new \InvalidArgumentException('Choose a bulk action.');
            }
            if (in_array($action, ['publish', 'unpublish'], true) && ! $publisher) {
                throw new \InvalidArgumentException('This action requires publication permission.');
            }
            $updated = 0;
            $errors = [];
            foreach ($ids as $id) {
                try {
                    $state = $this->documents->get('article', $id);
                    $expected = $input['versions'][$id] ?? [];
                    if ($state['version'] !== (int) ($expected['version'] ?? -1) || ! hash_equals($state['live_hash'], (string) ($expected['live_hash'] ?? ''))) {
                        throw new \InvalidArgumentException('This article changed. Refresh before updating it.');
                    }
                    $this->presence->assertEditor($id, (int) $actor->getId());
                    if ($action === 'publish') {
                        $this->documents->publish('article', $id, $state['version'], (string) $actor->getUserName());
                    } elseif ($action === 'unpublish') {
                        $post = $this->posts->post($id);
                        $this->posts->unpublish($id, $this->posts->fingerprint($post));
                    } else {
                        $payload = $state['payload'];
                        $meta = $this->metadata->get(['editorial' => $payload['editorial'] ?? []]);
                        if ($action === 'assign') {
                            $meta['assigned_to'] = (int) ($input['assigned_to'] ?? 0);
                        } else {
                            $meta['tag_ids'] = $this->metadata->ids($input['tag_ids'] ?? []);
                        }$payload['editorial'] = $meta;
                        $this->documents->save('article', $id, $payload, $state['version'], (string) $actor->getUserName(), 'draft', $state['live_hash']);
                    }
                    $updated++;
                } catch (LocalizedException|\InvalidArgumentException $exception) {
                    $errors[] = ['id' => $id, 'message' => $exception->getMessage()];
                } catch (\Throwable $exception) {
                    $this->logger->error('Bulk blog update failed.', ['post_id' => $id, 'exception' => $exception]);
                    $errors[] = ['id' => $id, 'message' => 'This article could not be updated.'];
                }
            }
            if ($updated) {
                $this->lifecycle->refresh();
            }

            return ['message' => $updated.' of '.count($ids).' articles updated.', 'errors' => $errors];
        }
        if ($intent === 'export') {
            return ['package' => $this->transfer->export($this->metadata->ids($input['ids'] ?? []))];
        }
        if ($intent === 'import') {
            if (! $publisher || ! $designer) {
                throw new \InvalidArgumentException('Importing article designs requires design and publication permission.');
            }
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > 20000000) {
                throw new \InvalidArgumentException('Choose a blog JSON package up to 20 MB.');
            }

            return $this->transfer->import(file_get_contents($file['tmp_name']), (string) $actor->getUserName());
        }
        if (! $publisher) {
            throw new \InvalidArgumentException('Managing public blog settings requires publication permission.');
        }
        if ($intent === 'taxonomy') {
            $id = $this->taxonomy->save((string) ($input['kind'] ?? ''), (int) ($input['id'] ?? 0), $input);
            $this->lifecycle->refresh();

            return ['id' => $id];
        }
        if ($intent === 'moderation') {
            return ['feedback' => $this->engagement->moderation()];
        }
        if ($intent === 'moderate') {
            $this->engagement->moderate((int) ($input['id'] ?? 0), (string) ($input['status'] ?? ''), (string) ($input['previous'] ?? ''));

            return ['ok' => true];
        }
        if ($intent === 'analytics') {
            return ['metrics' => $this->engagement->analytics()];
        }
        if ($intent === 'delivery') {
            return ['subscribers' => $this->subscriptionsSummary(), 'mail' => $this->delivery->queue(), 'settings' => $this->settings->get()];
        }
        if ($intent === 'retry_mail') {
            $this->delivery->retry((int) ($input['id'] ?? 0));

            return ['ok' => true];
        }
        if ($intent === 'settings') {
            $this->settings->save($input);
            $this->lifecycle->refresh();

            return ['settings' => $this->settings->get()];
        }
        if ($intent === 'media') {
            return ['media' => $this->images->listing()];
        }
        if ($intent === 'upload') {
            return ['image' => $this->images->upload($file ?? [])];
        }
        throw new \InvalidArgumentException('Choose a valid blog action.');
    }

    public function staff(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->database->getTableName('admin_user'), ['user_id', 'username'])->where('is_active = ?', 1)->order('username ASC'));
    }

    public function options(): array
    {
        return array_map(fn ($row) => ['id' => (int) $row['post_id'], 'title' => $row['title']], $this->posts->listing([], 1, 100, false)['items']);
    }

    private function subscriptionsSummary(): array
    {
        $db = $this->database->getConnection();

        return $db->fetchAll($db->select()->from($this->database->getTableName('shi3ld_blog_subscriber'), ['status', 'count' => new \Zend_Db_Expr('COUNT(*)')])->group('status'));
    }

    private function editorUrl(string $type, int $id): string
    {
        return $this->backend->getUrl('shi3ld_content/editor/index', ['type' => $type, 'id' => $id]);
    }
}
