<?php

declare(strict_types=1);

namespace Shi3ld\Catalog\Model\Transfer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class Package
{
    public const DIRECTORY = 'shi3ld/catalogue-transfer';
    public const MAX_PRODUCTS = 1000;
    public const MAX_BYTES = 268435456;
    private const MAX_ENTRY_BYTES = 33554432;
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];

    private WriteInterface $directory;

    public function __construct(Filesystem $filesystem)
    {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function path(string $relative): string
    {
        if (!preg_match('#\A[a-zA-Z0-9_./-]+\z#D', $relative) || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new \InvalidArgumentException('Invalid catalogue transfer path.');
        }
        $this->directory->create(self::DIRECTORY);
        $this->directory->changePermissions(self::DIRECTORY, 0700);
        return $this->directory->getAbsolutePath(self::DIRECTORY . '/' . $relative);
    }

    public function write(array $catalogue, array $images, string $name): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\.zip\z/D', $name)) {
            throw new \InvalidArgumentException('Choose a ZIP filename containing letters, numbers, underscores or hyphens.');
        }
        $path = $this->path($name);
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the catalogue package.');
        }
        try {
            $json = json_encode($catalogue, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > self::MAX_ENTRY_BYTES) {
                throw new \RuntimeException('Catalogue metadata is too large for one package. Select fewer products.');
            }
            $zip->addFromString('catalogue.json', $json);
            foreach ($images as $asset => $file) {
                if (!$zip->addFile($file, $asset)) {
                    throw new \RuntimeException('Unable to package a product image.');
                }
            }
        } finally {
            if (!$zip->close()) {
                throw new \RuntimeException('Unable to finish the catalogue package.');
            }
        }
        chmod($path, 0600);
        $this->read($path);
        return $path;
    }

    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path) || filesize($path) > self::MAX_BYTES) {
            throw new \RuntimeException('Choose a readable catalogue ZIP smaller than 256 MB.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('The file is not a valid catalogue ZIP.');
        }
        try {
            $total = 0;
            $names = [];
            if ($zip->numFiles > 10000) {
                throw new \RuntimeException('The package contains too many files.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'];
                if (isset($names[$name]) || ($name !== 'catalogue.json' && !preg_match('#\Amedia/[a-f0-9]{64}\.(?:jpg|png|gif)\z#D', $name))) {
                    throw new \RuntimeException('The package contains an unexpected or duplicate file.');
                }
                $names[$name] = true;
                $total += $entry['size'];
                if ($entry['size'] > self::MAX_ENTRY_BYTES || $total > self::MAX_BYTES) {
                    throw new \RuntimeException('The expanded package is too large. Select fewer products.');
                }
            }
            $json = $zip->getFromName('catalogue.json');
            if ($json === false) {
                throw new \RuntimeException('The package does not contain catalogue.json.');
            }
            $data = json_decode($json, true, 48, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['format'] ?? '') !== 'shi3ld.catalogue' || ($data['version'] ?? null) !== 1
                || !is_array($data['products'] ?? null) || count($data['products']) < 1 || count($data['products']) > self::MAX_PRODUCTS
                || !is_array($data['attributes'] ?? null) || !is_array($data['attribute_sets'] ?? null)
                || !is_array($data['categories'] ?? null) || !is_array($data['source'] ?? null)
                || count($data['attributes']) > 1000 || count($data['attribute_sets']) > 1000 || count($data['categories']) > 10000
                || !in_array($data['source']['environment'] ?? '', ['development', 'staging', 'production'], true)
                || !is_string($data['source']['host'] ?? null) || !is_string($data['source']['currency'] ?? null)
            ) {
                throw new \RuntimeException('The catalogue package format is invalid or unsupported.');
            }
            foreach ($data['products'] as $product) {
                foreach ($product['media'] ?? [] as $image) {
                    $asset = $image['asset'] ?? '';
                    if (!isset($names[$asset])) {
                        throw new \RuntimeException('A product image is missing from the package.');
                    }
                    $bytes = $zip->getFromName($asset);
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                    if (!isset(self::IMAGE_TYPES[$mime]) || $mime !== ($image['mime'] ?? '')
                        || !hash_equals((string) ($image['sha256'] ?? ''), hash('sha256', $bytes))
                        || $asset !== 'media/' . $image['sha256'] . '.' . self::IMAGE_TYPES[$mime]
                        || @getimagesizefromstring($bytes) === false
                    ) {
                        throw new \RuntimeException('A product image failed its integrity check.');
                    }
                }
            }
            return $data;
        } finally {
            $zip->close();
        }
    }

    public function image(string $path, string $asset): string
    {
        if (!preg_match('#\Amedia/[a-f0-9]{64}\.(?:jpg|png|gif)\z#D', $asset)) {
            throw new \RuntimeException('Invalid product image reference.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Unable to read the catalogue package.');
        }
        try {
            $bytes = $zip->getFromName($asset);
            if ($bytes === false) {
                throw new \RuntimeException('Unable to read a packaged image.');
            }
            return $bytes;
        } finally {
            $zip->close();
        }
    }
}
