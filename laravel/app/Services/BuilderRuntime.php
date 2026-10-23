<?php

namespace App\Services;

use App\Services\Builder\Engine;
use App\Services\Builder\Schema;
use Illuminate\Support\Facades\Http;

class BuilderRuntime
{
    public function __construct(private BuilderLibrary $library, private Engine $engine, private Schema $schema) {}

    public function context(string $key = 'services', array $fields = []): array
    {
        return ['key' => $key, 'kind' => 'services', 'path' => '/'.request()->path(),
            'audience' => auth()->user()?->services_staff ? 'staff' : (auth()->check() ? 'customer' : 'guest'), 'fields' => $fields];
    }

    public function assets(): array
    {
        $assets = $this->library->published();
        $preview = request()->attributes->get('builder_asset_preview');
        if (is_array($preview)) {
            $assets[$preview['key']] = $preview['payload'];
        }

        return $assets;
    }

    public function prepare(string $html): string
    {
        $html = $this->schema->portableHtml($html);

        return preg_replace_callback('~<div\b([^>]*data-builder-remote-widget="(blog|products)"[^>]*)>\s*</div>~', function (array $match): string {
            try {
                $options = [];
                if (preg_match('/data-builder-remote-options="([^"]*)"/', $match[1], $attributes)) {
                    $options = json_decode(html_entity_decode($attributes[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, 8, JSON_THROW_ON_ERROR);
                }
                $options['kind'] = $match[2];
                $headers = ['X-Forwarded-Proto' => (string) parse_url(config('shi3ld.site_url'), PHP_URL_SCHEME)];
                if ($host = parse_url(config('shi3ld.site_url'), PHP_URL_HOST)) {
                    $headers['Host'] = $host;
                }
                $result = Http::connectTimeout(2)->timeout(4)->withoutRedirecting()->acceptJson()->withHeaders($headers)
                    ->get(rtrim(config('shi3ld.magento.base_url'), '/').'/shi3ld_content/builder/widget', $options);

                $html = $result->successful() ? (string) $result->json('html', '') : '<a href="/'.($match[2] === 'blog' ? 'blog' : 'shop').'">Explore more</a>';

                return '<div'.$match[1].'>'.$html.'</div>';
            } catch (\Throwable $exception) {
                report($exception);

                return '<div'.$match[1].'><a href="/'.($match[2] === 'blog' ? 'blog' : 'shop').'">Explore more</a></div>';
            }
        }, $html);
    }

    public function lookup(string $key): ?array
    {
        $asset = $this->assets()[$key] ?? null;
        if ($asset) {
            $asset['html'] = $this->prepare($asset['html']);
        }

        return $asset;
    }

    public function render(string $html, array $settings, array $context, bool $editing = false): array
    {
        return $this->engine->render($this->prepare($html), $settings, $context, fn (string $key) => $this->lookup($key), $editing);
    }

    public function wrap(string $html, array $settings, array $context): string
    {
        if ($settings === [] && ! str_contains($html, 'data-builder-')) {
            return $html;
        }
        $result = $this->render($html, $settings, $context, ! empty($context['editing']));

        return ($result['css'] !== '' ? '<style data-builder-stylesheet="page">'.$result['css'].'</style>' : '').$result['html'];
    }

    public function region(string $region, string $html): string
    {
        return $this->template($region, $this->wrap($html, $this->library->regionSettings($region), $this->context('shared-'.$region)));
    }

    public function template(string $target, string $html, array $context = []): string
    {
        $context = array_replace_recursive($this->context(), $context);
        $matches = [];
        foreach ($this->assets() as $key => $asset) {
            $options = $asset['builder']['asset'] ?? [];
            if (($options['kind'] ?? '') !== 'template' || ! in_array($options['target'] ?? 'all', [$target, 'all'], true)) {
                continue;
            }
            if (in_array($target, ['header', 'footer'], true) && ($options['target'] ?? '') !== $target) {
                continue;
            }
            $preview = request()->attributes->get('builder_asset_preview');
            if (($preview && $preview['key'] === $key) || $this->schema->matches($asset['builder']['conditions'] ?? [], $context)) {
                $matches[] = $asset;
            }
        }
        usort($matches, fn (array $a, array $b) => ($b['builder']['asset']['priority'] ?? 10) <=> ($a['builder']['asset']['priority'] ?? 10));
        if (! $matches) {
            return $html;
        }
        $asset = $matches[0];

        return $this->wrap($this->engine->template($this->prepare($asset['html']), $html), $asset['builder'], array_replace($context, ['key' => $target.'-'.($asset['asset_key'] ?? 'template')]));
    }

    public function globalHtml(): string
    {
        $css = '';
        foreach ($this->assets() as $asset) {
            if (($asset['builder']['asset']['kind'] ?? '') === 'global' && $this->schema->matches($asset['builder']['conditions'] ?? [], $this->context())) {
                $css .= $this->engine->globalCss($asset['builder']);
            }
        }

        return $css === '' ? '' : '<style data-builder-stylesheet="global">'.$css.'</style>';
    }

    public function popups(): string
    {
        $html = '';
        $context = $this->context();
        foreach ($this->assets() as $key => $asset) {
            $options = $asset['builder']['asset'] ?? [];
            if (($options['kind'] ?? '') !== 'popup' || ! $this->schema->matches($asset['builder']['conditions'] ?? [], $context)) {
                continue;
            }
            $html .= '<div data-builder-popup="'.e(json_encode($options + ['key' => $key], JSON_THROW_ON_ERROR)).'" hidden>'.$this->wrap($this->prepare($asset['html']), $asset['builder'], array_replace($context, ['key' => 'popup-'.$key])).'</div>';
        }

        return $html;
    }
}
