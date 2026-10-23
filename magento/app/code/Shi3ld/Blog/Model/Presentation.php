<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Presentation
{
    public function __construct(private Filesystem $filesystem, private AdapterFactory $images, private StoreManagerInterface $stores,
        private LoggerInterface $logger) {}

    public function excerpt(array $post, int $length = 150): string
    {
        $text = trim((string) ($post['excerpt'] ?? ''));
        if ($text === '') {
            $text = preg_replace('/\{\{.*?\}\}/s', '', (string) ($post['content'] ?? ''));
            $text = preg_replace('~<(?:style|script)\b[^>]*>.*?</(?:style|script)>~is', '', $text);
            $text = preg_replace('~</(?:p|div|h[1-6]|li|section)>|<br\s*/?>~i', ' ', $text);
        }
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($length === 0) {
            return '';
        }

        return mb_strlen($text) <= $length ? $text : rtrim(mb_substr($text, 0, $length), ' ,.;:').'…';
    }

    public function image(string $path, int $width = 640): string
    {
        $fallback = '/services/assets/blog-placeholder.svg';
        if ($path === '' || ! preg_match('~^wysiwyg/[a-zA-Z0-9_./-]+\.(?:jpe?g|png|gif|webp)$~iD', $path) || str_contains($path, '..')) {
            return $fallback;
        }
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        if (! $directory->isFile($path)) {
            return $fallback;
        }
        $width = max(320, min(1600, $width));
        $key = hash('sha256', $path.':'.($directory->stat($path)['mtime'] ?? '').':'.$width);
        $target = 'shi3ld/blog-cache/'.$key.'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $temporary = null;
        try {
            if (! $directory->isFile($target)) {
                $directory->create(dirname($target));
                $adapter = $this->images->create();
                $adapter->open($directory->getAbsolutePath($path));
                $adapter->keepAspectRatio(true);
                $adapter->keepTransparency(true);
                $adapter->keepFrame(false);
                $adapter->constrainOnly(true);
                $adapter->quality(85);
                $adapter->resize($width, null);
                $temporary = dirname($target).'/'.$key.'-'.bin2hex(random_bytes(6)).'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $adapter->save($directory->getAbsolutePath(dirname($temporary)), basename($temporary));
                $directory->renameFile($temporary, $target);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Blog thumbnail generation failed; using original image.', ['exception' => $exception]);
            if ($temporary !== null && $directory->isFile($temporary)) {
                $directory->delete($temporary);
            }
            $target = $path;
        }

        return rtrim($this->stores->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/').'/'.implode('/', array_map('rawurlencode', explode('/', $target)));
    }

    public function srcset(string $path): string
    {
        return implode(', ', array_map(fn ($width) => $this->image($path, $width).' '.$width.'w', [480, 800, 1280]));
    }
}
