<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Request\Http;
use Magento\User\Model\User;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Model\Editor\Markup;

class Commands
{
    public const INTENTS = ['assets', 'create_asset', 'load_asset', 'unpublish_asset', 'export_design', 'import_design', 'submit_review', 'schedule', 'cancel_schedule', 'review_queue'];

    public function __construct(private Library $library, private Schema $schema, private Engine $engine,
        private Documents $documents, private Markup $markup,
        private UrlInterface $urls, private Workflow $workflow, private Runtime $runtime,
        private TypeListInterface $cache, private Package $packages) {}

    public function configuration(bool $designer): array
    {
        return ['canDesign' => $designer, 'advanced' => ['properties' => Schema::PROPERTIES, 'fields' => Schema::FIELDS,
            'kinds' => Schema::KINDS, 'targets' => Schema::TARGETS, 'libraryUrl' => $this->urls->getUrl('shi3ld_content/library/index')]];
    }

    public function assert(array $payload, array $baseline, bool $designer): void
    {
        $this->engine->protect($payload['html'], $baseline['html'], $designer);
        if (! $designer && $this->schema->settings($payload['builder'] ?? []) !== $this->schema->settings($baseline['builder'] ?? [])) {
            throw new \InvalidArgumentException('Design settings require design permission.');
        }
    }

    public function run(string $intent, string $type, int $id, array $current, Http $request,
        User $actor, bool $publisher, bool $designer): array
    {
        if ($intent === 'assets') {
            return ['assets' => array_map(function ($row): array {
                $payload = $this->documents->get('asset', (int) $row['asset_id'])['payload'];

                return ['id' => (int) $row['asset_id'], 'key' => $row['asset_key'], 'kind' => $row['kind'], 'title' => $row['title'],
                    'published' => $row['live_payload'] !== null, 'payload' => $payload, 'url' => $this->urls->getUrl('shi3ld_content/editor/index', ['type' => 'asset', 'id' => $row['asset_id']])];
            }, $this->library->all()), 'workflow' => $this->workflow->get($type, $id)];
        }
        if ($intent === 'load_asset') {
            $row = $this->library->row((int) $request->getParam('asset_id'));

            return ['payload' => $this->documents->get('asset', (int) $row['asset_id'])['payload'], 'key' => $row['asset_key']];
        }
        if ($intent === 'submit_review' || $intent === 'schedule') {
            if ($intent === 'schedule' && ! $publisher) {
                throw new \InvalidArgumentException('Scheduling requires publication permission.');
            }

            return ['workflow' => $this->workflow->request($type, $id, (int) $request->getParam('version'), $actor, $intent === 'schedule' ? (string) $request->getParam('scheduled_at') : null)];
        }
        if ($intent === 'cancel_schedule') {
            $this->workflow->cancel($type, $id);

            return ['workflow' => $this->workflow->get($type, $id)];
        }
        if ($intent === 'review_queue') {
            if (! $publisher) {
                throw new \InvalidArgumentException('Reviewing requires publication permission.');
            }

return ['requests' => $this->workflow->pending()];
        }
        if ($intent === 'unpublish_asset') {
            if (! $publisher) {
                throw new \InvalidArgumentException('Unpublishing requires publication permission.');
            }
            $this->library->unpublish((int) $request->getParam('asset_id'));
            $this->runtime->export();
            $this->cache->cleanType('full_page');
            $this->cache->cleanType('block_html');

            return ['ok' => true];
        }
        if (! $designer) {
            throw new \InvalidArgumentException('Managing reusable designs requires design permission.');
        }
        if ($intent === 'create_asset') {
            $kind = (string) $request->getParam('kind', 'section');
            $title = (string) $request->getParam('title', 'New design');
            $payload = $request->getParam('asset_payload') ? json_decode((string) $request->getParam('asset_payload'), true, 32, JSON_THROW_ON_ERROR) : null;
            if ($payload) {
                $payload = $this->documents->validate($payload, $current['payload']['html']);
                $payload['builder'] = $this->schema->settings($payload['builder'] ?? []);
                $payload['builder']['asset'] = $this->schema->settings(['asset' => ['kind' => $kind]])['asset'];
                $payload['title'] = $title;
            }
            $assetId = $this->library->create($kind, $title, $payload);
            $row = $this->library->row($assetId);

            return ['id' => $assetId, 'key' => $row['asset_key'], 'url' => $this->urls->getUrl('shi3ld_content/editor/index', ['type' => 'asset', 'id' => $assetId])];
        }
        if ($intent === 'export_design') {
            return ['package' => $this->packages->export($current['payload'])];
        }
        if ($intent === 'import_design') {
            $file = (array) $request->getFiles('package');
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > 30000000) {
                throw new \InvalidArgumentException('Choose a builder JSON package up to 30 MB.');
            }
            $payload = $this->packages->import(file_get_contents($file['tmp_name']), $current['payload']['html']);

            return ['payload' => $payload];
        }
        throw new \InvalidArgumentException('Choose a valid builder action.');
    }
}
