<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Builder;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Shi3ld\Content\Model\Editor\Markup;

class Package
{
    public function __construct(private Library $library, private Filesystem $filesystem,
        private Markup $markup, private Schema $schema,
        private ResourceConnection $database) {}

    public function export(array $payload): array
    {
        $assets = [];
        $collect = function (string $html, int $depth = 0) use (&$collect, &$assets): void {
            if ($depth > 8) {
                throw new \InvalidArgumentException('Too many nested reusable designs.');
            }
            preg_match_all('/data-builder-ref="([a-f0-9]{32})"/', $html, $matches);
            foreach ($matches[1] as $key) {
                if (isset($assets[$key])) {
                    continue;
                }
                if ($item = $this->library->exportable($key)) {
                    $assets[$key] = $item;
                    $collect($item['html'], $depth + 1);
                }
            }
        };
        $collect($payload['html']);
        $files = [];
        $total = 0;
        $serialized = json_encode([$payload, $assets], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        preg_match_all('~(?:/media/|url[=\\"\x27 ]+)(wysiwyg/[a-zA-Z0-9_./%-]+\.(?:jpe?g|png|gif|webp))~i', $serialized, $matches);
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        foreach (array_unique($matches[1]) as $path) {
            if (str_contains($path, '..') || ! $directory->isFile($path)) {
                continue;
            }
            $data = $directory->readFile($path);
            $total += strlen($data);
            if (strlen($data) > 5242880 || $total > 20000000) {
                throw new \InvalidArgumentException('The design contains more than 20 MB of media. Export smaller sections.');
            }
            $files[$path] = ['sha256' => hash('sha256', $data), 'data' => base64_encode($data)];
        }

        return ['format' => 'shi3ld-builder', 'version' => 1, 'framework' => 'magento', 'payload' => $payload, 'assets' => $assets, 'media' => $files];
    }

    public function import(string $json, string $baseline): array
    {
        if (strlen($json) > 30000000) {
            throw new \InvalidArgumentException('The builder package is too large.');
        }
        $package = json_decode($json, true, 48, JSON_THROW_ON_ERROR);
        if (! is_array($package) || ($package['format'] ?? '') !== 'shi3ld-builder' || ($package['version'] ?? 0) !== 1
            || ! is_array($package['payload'] ?? null) || ! is_array($package['assets'] ?? []) || ! is_array($package['media'] ?? [])
            || count($package['assets'] ?? []) > 100 || count($package['media'] ?? []) > 100) {
            throw new \InvalidArgumentException('Choose a supported Shi3ld builder package.');
        }
        $mapping = [];
        $total = 0;
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach ($package['media'] ?? [] as $original => $file) {
            if (! is_string($original) || ! preg_match('~^(?:wysiwyg/|/media/|/services/assets/)[a-zA-Z0-9_./% -]+\.(?:jpe?g|png|gif|webp)$~iD', $original) || str_contains($original, '..') || ! is_array($file)) {
                throw new \InvalidArgumentException('A package image has an invalid path.');
            }
            $data = base64_decode($file['data'] ?? '', true);
            if ($data === false || strlen($data) > 5242880 || ! hash_equals((string) ($file['sha256'] ?? ''), hash('sha256', $data))) {
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
            $target = 'wysiwyg/shi3ld/builder/'.hash('sha256', $data).'.'.$extension;
            $directory->create(dirname($target));
            $directory->writeFile($target, $data);
            $mapping[str_starts_with($original, 'wysiwyg/') ? '/media/'.$original : $original] = '/media/'.$target;
        }
        $remap = function (array $payload) use (&$mapping): array {
            $payload['html'] = preg_replace_callback('~\{\{media\s+url\s*=\s*[\x22\x27]?([^\x22\x27}]*?)[\x22\x27]?\s*\}\}~', fn (array $match) => '/media/'.ltrim(trim($match[1]), '/'), (string) ($payload['html'] ?? ''));

            return json_decode(strtr(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $mapping), true, 48, JSON_THROW_ON_ERROR);
        };
        $db = $this->database->getConnection();
        $db->beginTransaction();
        try {
            $created = [];
            foreach ($package['assets'] ?? [] as $oldKey => $asset) {
                if (! is_string($oldKey) || ! preg_match('/^[a-f0-9]{32}$/D', $oldKey) || ! is_array($asset)) {
                    throw new \InvalidArgumentException('A linked design has an invalid identifier.');
                }
                $asset = $remap($asset);
                $asset['html'] = $this->markup->validate((string) $asset['html'], $baseline);
                $asset['builder'] = $this->schema->settings($asset['builder'] ?? []);
                $id = $this->library->create($asset['builder']['asset']['kind'] ?? 'section', (string) ($asset['title'] ?? ''), $asset);
                $created[$oldKey] = $id;
                $mapping[$oldKey] = $this->library->row($id)['asset_key'];
            }
            foreach ($created as $oldKey => $id) {
                $asset = $remap($package['assets'][$oldKey]);
                $asset['html'] = $this->markup->validate((string) $asset['html'], $baseline);
                $asset['builder'] = $this->schema->settings($asset['builder'] ?? []);
                $this->library->initial($id, $asset);
            }
            $payload = $remap($package['payload']);
            $payload['html'] = $this->markup->validate((string) ($payload['html'] ?? ''), $baseline);
            $payload['builder'] = $this->schema->settings($payload['builder'] ?? []);
            $this->schema->templateContent($payload['builder'], $payload['html']);
            $db->commit();

            return $payload;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
