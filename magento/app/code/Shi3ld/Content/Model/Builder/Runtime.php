<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Cms\Model\Page;
use Magento\Customer\Model\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Shi3ld\Content\Model\Editor\Preview;
use Shi3ld\Content\Model\Editor\Renderer;

class Runtime
{
    public function __construct(private Library $library, private State $state, private Schema $schema, private Engine $engine,
        private Renderer $renderer, private Preview $preview,
        private Http $request, private Filesystem $filesystem,
        private ResourceConnection $database, private \Magento\Framework\App\Http\Context $visitor,
        private Page $page, private Registry $registry, private array $contextProviders = []) {}

    public function context(array $override = []): array
    {
        $path = '/'.ltrim((string) $this->request->getOriginalPathInfo(), '/');
        $context = ['key' => 'page-'.(int) $this->page->getId(), 'path' => $path, 'kind' => 'cms',
            'audience' => $this->preview->get() ? 'staff' : ($this->visitor->getValue(Context::CONTEXT_AUTH) ? 'customer' : 'guest'),
            'fields' => ['title' => (string) $this->page->getTitle(), 'url' => $path]];
        if ($product = $this->registry->registry('current_product')) {
            $context['kind'] = 'product';
            $context['key'] = 'product-'.(int) $product->getId();
            $image = (string) $product->getData('image');
            $image = $image !== '' && $image !== 'no_selection' ? $product->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA).'catalog/product/'.ltrim($image, '/') : '/services/assets/blog-placeholder.svg';
            $context['fields'] = array_replace($context['fields'], ['title' => (string) $product->getName(), 'url' => $product->getProductUrl(), 'image' => $image,
                'product.name' => (string) $product->getName(), 'product.sku' => (string) $product->getSku(), 'product.image' => $image,
                'product.price' => number_format((float) $product->getFinalPrice(), 2), 'product.url' => $product->getProductUrl()]);
        }
        foreach ($this->contextProviders as $provider) {
            $context = array_replace_recursive($context, $provider->context());
        }

        return array_replace_recursive($context, $override);
    }

    public function assets(): array
    {
        $items = [];
        foreach ($this->library->all(true) as $row) {
            $items[$row['asset_key']] = json_decode($row['live_payload'], true, 32, JSON_THROW_ON_ERROR) + ['asset_key' => $row['asset_key']];
        }
        $draft = $this->preview->get();
        if ($draft && $draft['type'] === 'asset') {
            $items[$draft['identifier']] = $draft['payload'] + ['asset_key' => $draft['identifier']];
        }

        return $items;
    }

    public function lookup(string $key): ?array
    {
        $item = $this->assets()[$key] ?? null;
        if ($item) {
            $item['html'] = $this->renderer->render($item['html']);
        }

        return $item;
    }

    public function settings(string $type, int $id): array
    {
        $draft = $this->preview->get();
        if ($draft && $draft['type'] === $type && (int) $draft['id'] === $id) {
            return $draft['payload']['builder'] ?? [];
        }

        return $this->state->get($type, $id) ?? [];
    }

    public function blockId(string $identifier): int
    {
        $db = $this->database->getConnection();

        return (int) $db->fetchOne($db->select()->from($this->database->getTableName('cms_block'), ['block_id'])->where('identifier = ?', $identifier));
    }

    public function render(string $html, array $settings, array $context = [], bool $editing = false): array
    {
        $context = $this->context($context);
        if (($settings['asset']['kind'] ?? '') === 'global') {
            return ['html' => $html, 'css' => $this->engine->globalCss($settings)];
        }
        if (($settings['asset']['kind'] ?? '') === 'form' && $editing && ! empty($context['asset_key'])) {
            $form = $this->schema->settings($settings)['asset']['form'];
            $html .= '<div data-builder-form="'.htmlspecialchars($context['asset_key'], ENT_QUOTES, 'UTF-8').'" data-builder-form-preview="'.htmlspecialchars(json_encode($form, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8').'"></div>';
        }

        return $this->engine->render($html, $settings, $context, fn ($key) => $this->lookup($key), $editing);
    }

    public function wrap(string $html, array $settings, array $context = []): string
    {
        if ($settings === [] && ! str_contains($html, 'data-builder-')) {
            return $html;
        }
        $result = $this->render($html, $settings, $context, ! empty($context['editing']));

        return ($result['css'] !== '' ? '<style data-builder-stylesheet="page">'.$result['css'].'</style>' : '').$result['html'];
    }

    public function match(string $kind, array $context = []): ?array
    {
        $context = $this->context($context);
        $matches = [];
        foreach ($this->assets() as $key => $item) {
            $options = $item['builder']['asset'] ?? [];
            if (! in_array($options['kind'] ?? '', ['template', 'product'], true)) {
                continue;
            }
            if (($options['kind'] ?? '') === 'product' && $kind !== 'product') {
                continue;
            }
            if ($kind === 'product' && ($options['kind'] ?? '') !== 'product') {
                continue;
            }
            if (in_array($kind, ['header', 'footer'], true) && ($options['target'] ?? '') !== $kind) {
                continue;
            }
            if (($options['target'] ?? 'all') !== 'all' && ($options['target'] ?? '') !== $kind) {
                continue;
            }
            $draft = $this->preview->get();
            $force = $draft && $draft['type'] === 'asset' && $draft['identifier'] === $key;
            if ($force || $this->schema->matches($item['builder']['conditions'] ?? [], $context)) {
                $matches[] = $item;
            }
        }
        usort($matches, fn ($a, $b) => ($b['builder']['asset']['priority'] ?? 10) <=> ($a['builder']['asset']['priority'] ?? 10));

        return $matches[0] ?? null;
    }

    public function template(string $kind, string $html, array $context = []): string
    {
        $template = $this->match($kind, $context);
        if (! $template || ($template['builder']['asset']['kind'] ?? '') === 'product') {
            return $html;
        }
        $content = $this->engine->template($this->renderer->render($template['html']), $html);

        return $this->wrap($content, $template['builder'], array_replace_recursive($this->context($context), ['key' => $kind.':'.$template['asset_key']]));
    }

    public function globalHtml(): string
    {
        $css = '';
        foreach ($this->assets() as $item) {
            if (($item['builder']['asset']['kind'] ?? '') === 'global' && $this->schema->matches($item['builder']['conditions'] ?? [], $this->context())) {
                $css .= $this->engine->globalCss($item['builder']);
            }
        }

        return $css === '' ? '' : '<style data-builder-stylesheet="global">'.$css.'</style>';
    }

    public function popups(): string
    {
        $html = '';
        $context = $this->context();
        foreach ($this->assets() as $key => $item) {
            $options = $item['builder']['asset'] ?? [];
            if (($options['kind'] ?? '') !== 'popup' || ! $this->schema->matches($item['builder']['conditions'] ?? [], $context)) {
                continue;
            }
            $content = $this->wrap($this->renderer->render($item['html']), $item['builder'], ['key' => 'popup-'.$key]);
            $config = htmlspecialchars(json_encode($options + ['key' => $key], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
            $html .= '<div data-builder-popup="'.$config.'" hidden>'.$content.'</div>';
        }

        return $html;
    }

    public function export(): void
    {
        $assets = [];
        foreach ($this->library->all(true) as $row) {
            $assets[$row['asset_key']] = json_decode($row['live_payload'], true, 32, JSON_THROW_ON_ERROR) + ['asset_key' => $row['asset_key'], '_id' => (int) $row['asset_id']];
        }
        $regions = [];
        foreach (['header', 'footer'] as $region) {
            $regions[$region] = $this->state->get('block', $this->blockId('shi3ld_global_'.$region)) ?? [];
        }
        $data = ['version' => 1, 'assets' => $assets, 'regions' => $regions, 'updated_at' => gmdate(DATE_ATOM)];
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('shi3ld/site-access');
        $path = 'shi3ld/site-access/builder.json';
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        $directory->writeFile($temporary, $encoded);
        $directory->changePermissions($temporary, 0644);
        $directory->renameFile($temporary, $path);
    }

    public function privateRules(): bool
    {
        foreach ($this->assets() as $item) {
            if (($item['builder']['conditions']['audience'] ?? 'all') !== 'all' || preg_match('/data-builder-conditions=[\x22\x27][^>]*(?:guest|customer|staff)/', $item['html'])) {
                return true;
            }
        }
        $db = $this->database->getConnection();
        foreach ($db->fetchCol($db->select()->from($this->database->getTableName('shi3ld_builder_page'), ['settings'])) as $json) {
            $settings = json_decode($json, true);
            if (! empty($settings['has_audience_rules']) || ($settings['conditions']['audience'] ?? 'all') !== 'all') {
                return true;
            }
        }

        return false;
    }
}
