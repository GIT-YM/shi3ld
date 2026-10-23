<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;

class Images
{
    public function __construct(
        private Filesystem $filesystem,
        private \Magento\MediaGalleryApi\Api\GetAssetsByPathsInterface $assets,
        private \Magento\MediaGalleryApi\Api\SaveAssetsInterface $saveAssets,
        private \Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory $assetFactory
    ) {}

    public function listing(): array
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $result = [];
        if (!$directory->isDirectory('wysiwyg')) {
            return $result;
        }
        foreach ($directory->readRecursively('wysiwyg') as $path) {
            if ($directory->isFile($path) && preg_match('/\.(?:jpe?g|png|gif|webp)$/i', $path)) {
                $result[] = $this->item($path);
            }
            if (count($result) >= 250) {
                break;
            }
        }
        return $result;
    }

    public function upload(array $file): array
    {
        $path = $file['tmp_name'] ?? '';
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($path)
            || ($file['size'] ?? 0) > 5242880) {
            throw new LocalizedException(__('Upload a JPEG, PNG, GIF or WebP image up to 5 MB.'));
        }
        $dimensions = @getimagesize($path);
        $extension = ['image/jpeg' => 'jpg','image/png' => 'png','image/gif' => 'gif','image/webp' => 'webp'][$dimensions['mime'] ?? ''] ?? null;
        if (!$extension || $dimensions[0] > 10000 || $dimensions[1] > 10000) {
            throw new LocalizedException(__('Choose an image no larger than 10,000 pixels on either side.'));
        }
        $target = 'wysiwyg/shi3ld/editor/'.hash_file('sha256', $path).'.'.$extension;
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        if (!$directory->isFile($target)) {
            $directory->writeFile($target, file_get_contents($path));
        }
        if (!$this->assets->execute([$target])) {
            $this->saveAssets->execute([$this->assetFactory->create([
                'path' => $target, 'contentType' => $dimensions['mime'], 'width' => $dimensions[0],
                'height' => $dimensions[1], 'size' => (int) $file['size'], 'title' => basename((string) ($file['name'] ?? 'Website image')),
            ])]);
        }
        return $this->item($target);
    }

    private function item(string $path): array
    {
        return ['url' => '/media/'.implode('/', array_map('rawurlencode', explode('/', $path))),
            'source' => '{{media url='.$path.'}}', 'name' => basename($path)];
    }
}
