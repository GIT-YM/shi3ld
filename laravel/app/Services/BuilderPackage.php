<?php

namespace App\Services;

use App\Services\Builder\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BuilderPackage
{
    public function __construct(private BuilderLibrary $library, private ContentMarkup $markup, private Schema $schema) {}

    public function export(array $payload): array
    {
        $assets = [];
        $collect = function (string $html, int $depth = 0) use (&$collect, &$assets): void {
            if ($depth > 8) {
                throw new \InvalidArgumentException('Too many nested reusable blocks.');
            }
            preg_match_all('/data-builder-ref="([a-f0-9]{32})"/', $html, $matches);
            foreach ($matches[1] as $key) {
                if (isset($assets[$key])) {
                    continue;
                }
                if ($asset = $this->library->get($key)) {
                    $assets[$key] = $asset;
                    $collect($asset['html'], $depth + 1);
                }
            }
        };
        $collect($payload['html']);
        $files = [];
        $total = 0;
        preg_match_all('~(/services/assets/[a-zA-Z0-9_./%-]+\.(?:jpe?g|png|gif|webp))~i', json_encode([$payload, $assets], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $matches);
        foreach (array_unique($matches[1]) as $url) {
            $path = realpath(public_path(ltrim($url, '/')));
            $base = realpath(public_path('services/assets'));
            if (! $path || ! $base || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR) || ! is_file($path)) {
                continue;
            }
            $data = file_get_contents($path);
            $total += strlen($data);
            if (strlen($data) > 5242880 || $total > 20000000) {
                throw new \InvalidArgumentException('Export smaller sections; package media is limited to 20 MB.');
            }
            $files[$url] = ['sha256' => hash('sha256', $data), 'data' => base64_encode($data)];
        }

        return ['format' => 'shi3ld-builder', 'version' => 1, 'framework' => 'laravel', 'payload' => $payload, 'assets' => $assets, 'media' => $files];
    }

    private function portable(string $html): string
    {
        return $this->schema->portableHtml($html);
    }

    public function import(string $json, string $baseline): array
    {
        if (strlen($json) > 30000000) {
            throw new \InvalidArgumentException('Choose a package up to 30 MB.');
        }
        $package = json_decode($json, true, 48, JSON_THROW_ON_ERROR);
        if (! is_array($package) || ($package['format'] ?? '') !== 'shi3ld-builder' || ($package['version'] ?? 0) !== 1
            || ! is_array($package['payload'] ?? null) || ! is_array($package['assets'] ?? []) || ! is_array($package['media'] ?? [])
            || count($package['assets'] ?? []) > 100 || count($package['media'] ?? []) > 100) {
            throw new \InvalidArgumentException('Choose a supported Shi3ld design package.');
        }
        $mapping = [];
        $total = 0;
        foreach ($package['media'] ?? [] as $original => $file) {
            if (! is_string($original) || ! preg_match('~^(?:wysiwyg/|/media/|/services/assets/)[a-zA-Z0-9_./% -]+\.(?:jpe?g|png|gif|webp)$~iD', $original) || str_contains($original, '..') || ! is_array($file)) {
                throw new \InvalidArgumentException('A package image has an invalid path.');
            }
            $data = base64_decode($file['data'] ?? '', true);
            if (! $data || strlen($data) > 5242880 || ! hash_equals((string) ($file['sha256'] ?? ''), hash('sha256', $data))) {
                throw new \InvalidArgumentException('A package image failed its integrity check.');
            }
            $total += strlen($data);
            if ($total > 20000000) {
                throw new \InvalidArgumentException('Package media exceeds 20 MB.');
            }
            $info = @getimagesizefromstring($data);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
            if (! $extension || $info[0] > 10000 || $info[1] > 10000) {
                throw new \InvalidArgumentException('The package contains an unsupported image.');
            }
            $target = '/services/assets/content/'.hash('sha256', $data).'.'.$extension;
            File::ensureDirectoryExists(public_path('services/assets/content'));
            File::put(public_path(ltrim($target, '/')), $data);
            $mapping[str_starts_with($original, 'wysiwyg/') ? '/media/'.$original : $original] = $target;
        }

        return DB::connection('mysql')->transaction(function () use ($package, $mapping, $baseline): array {
            $ids = [];
            $replace = $mapping;
            $remap = function (array $payload) use (&$replace): array {
                $payload['html'] = $this->portable((string) ($payload['html'] ?? ''));

                return json_decode(strtr(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $replace), true, 48, JSON_THROW_ON_ERROR);
            };
            foreach ($package['assets'] ?? [] as $old => $asset) {
                if (! is_string($old) || ! preg_match('/^[a-f0-9]{32}$/D', $old) || ! is_array($asset)) {
                    throw new \InvalidArgumentException('A linked design has an invalid identifier.');
                }
                $asset = $remap($asset);
                $asset['html'] = $this->markup->validate((string) $asset['html'], $baseline);
                $asset['builder'] = $this->schema->settings($asset['builder'] ?? []);
                $id = $this->library->create($asset['builder']['asset']['kind'] ?? 'section', (string) $asset['title'], $asset);
                $ids[$old] = $id;
                $replace[$old] = $this->library->row($id)->asset_key;
            }
            foreach ($ids as $old => $id) {
                $asset = $remap($package['assets'][$old]);
                $asset['html'] = $this->markup->validate($asset['html'], $baseline);
                $asset['builder'] = $this->schema->settings($asset['builder'] ?? []);
                DB::connection('mysql')->table('site_builder_assets')->where('id', $id)->update(['initial_payload' => json_encode($asset, JSON_THROW_ON_ERROR)]);
            }
            $payload = $remap($package['payload']);
            $payload['html'] = $this->markup->validate((string) ($payload['html'] ?? ''), $baseline);
            $payload['builder'] = $this->schema->settings($payload['builder'] ?? []);
            $this->schema->templateContent($payload['builder'], $payload['html']);

            return $payload;
        });
    }
}
