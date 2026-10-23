<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class Library
{
    public function __construct(private ResourceConnection $database, private Schema $schema) {}

    public function table(): string
    {
        return $this->database->getTableName('shi3ld_builder_asset');
    }

    public function row(int $id): array
    {
        $db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('asset_id = ?', $id));
        if (! $row) {
            throw new NoSuchEntityException(__('This builder design no longer exists.'));
        }

        return $row;
    }

    public function all(bool $published = false): array
    {
        $db = $this->database->getConnection();
        $select = $db->select()->from($this->table())->order('asset_id DESC');
        if ($published) {
            $select->where('live_payload IS NOT NULL');
        }

        return $db->fetchAll($select);
    }

    public function get(string $key): ?array
    {
        if (! preg_match('/^[a-f0-9]{32}$/D', $key)) {
            return null;
        }
        foreach ($this->all(true) as $row) {
            if ($row['asset_key'] === $key) {
                return json_decode($row['live_payload'], true, 32, JSON_THROW_ON_ERROR) + ['asset_key' => $key];
            }
        }

        return null;
    }

    public function snapshot(int $id): array
    {
        $row = $this->row($id);

        return json_decode($row['live_payload'] ?: $row['initial_payload'], true, 32, JSON_THROW_ON_ERROR);
    }

    public function exportable(string $key): ?array
    {
        if (! preg_match('/^[a-f0-9]{32}$/D', $key)) {
            return null;
        }$db = $this->database->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table())->where('asset_key = ?', $key));
        if (! $row) {
            return null;
        }
        $draft = $db->fetchOne($db->select()->from($this->database->getTableName('shi3ld_cms_document'), ['payload'])->where('entity_type = ?', 'asset')->where('entity_id = ?', (int) $row['asset_id']));

        return json_decode($draft ?: $row['live_payload'] ?: $row['initial_payload'], true, 32, JSON_THROW_ON_ERROR) + ['asset_key' => $key];
    }

    public function create(string $kind, string $title, ?array $payload = null): int
    {
        if (! in_array($kind, Schema::KINDS, true) || trim($title) === '' || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Choose a design type and title.');
        }
        $html = match ($kind) {
            'global' => '<div data-builder-live-preview="1"></div>',
            'template' => '<section class="section container"><div data-builder-slot="content"></div></section>',
            'card' => '<article class="builder-card"><img data-builder-field="image" src="/services/assets/blog-placeholder.svg" alt="Article image"><div><h3 data-builder-field="title">Article title</h3><p data-builder-field="excerpt">Article introduction</p><a data-builder-field="url" href="/blog">Read more ↗</a></div></article>',
            'form' => '<section class="section container"><h2>Tell us about your requirements</h2><p>Configure additional fields in the Design panel.</p></section>',
            'product' => '<section class="builder-product-preview"><div data-builder-slot="gallery">Native product gallery</div><div data-builder-slot="information">Native product information, options and add to basket</div><div data-builder-slot="details">Specifications and product description</div><div data-builder-slot="related">Related products</div></section>',
            'popup' => '<section class="section"><h2>Your announcement</h2><p>Add your message and choose where and when to display it.</p><a class="button" href="/services/quote">Get in touch</a></section>',
            default => '<section class="section container"><h2>Your reusable section</h2><p>Edit this section, then insert it as a copy or a linked block.</p></section>',
        };
        $payload ??= ['html' => $html, 'title' => $title, 'meta_title' => '', 'meta_description' => '', 'content_heading' => '',
            'builder' => $this->schema->settings(['asset' => ['kind' => $kind]])];
        $payload['builder'] = $this->schema->settings($payload['builder'] ?? ['asset' => ['kind' => $kind]]);
        $payload['builder']['asset']['kind'] = $kind;
        if ($kind === 'template' && ! str_contains($payload['html'], 'data-builder-slot')) {
            $payload['builder']['asset']['target'] = 'none';
        }
        $this->database->getConnection()->insert($this->table(), ['asset_key' => bin2hex(random_bytes(16)), 'kind' => $kind, 'title' => $title,
            'initial_payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);

        return (int) $this->database->getConnection()->lastInsertId($this->table());
    }

    public function publish(int $id, array $payload): void
    {
        $row = $this->row($id);
        $visit = function (string $key, array $item, array $stack = []) use (&$visit, $row, $payload): void {
            if (count($stack) > 8 || in_array($key, $stack, true)) {
                throw new \InvalidArgumentException('Linked designs contain a circular reference.');
            }
            $stack[] = $key;
            preg_match_all('/data-builder-ref="([a-f0-9]{32})"/', $item['html'], $matches);
            foreach ($matches[1] as $ref) {
                $child = $ref === $row['asset_key'] ? $payload : $this->get($ref);
                if ($child) {
                    $visit($ref, $child, $stack);
                }
            }
        };
        $visit($row['asset_key'], $payload);
        if (($payload['builder']['asset']['kind'] ?? '') !== $row['kind']) {
            throw new \InvalidArgumentException('The saved design type cannot be changed.');
        }
        $this->database->getConnection()->update($this->table(), ['title' => $payload['title'], 'live_payload' => json_encode($payload, JSON_THROW_ON_ERROR)], ['asset_id = ?' => $id]);
    }

    public function unpublish(int $id): void
    {
        $row = $this->row($id);
        $this->database->getConnection()->update($this->table(), ['initial_payload' => $row['live_payload'] ?: $row['initial_payload'], 'live_payload' => null], ['asset_id = ?' => $id]);
    }

    public function initial(int $id, array $payload): void
    {
        $this->database->getConnection()->update($this->table(), ['initial_payload' => json_encode($payload, JSON_THROW_ON_ERROR)], ['asset_id = ?' => $id]);
    }
}
