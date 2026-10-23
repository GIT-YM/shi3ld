<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class SiteAccess
{
    public const DIRECTORY = 'shi3ld/site-access';
    public const STATE_FILE = self::DIRECTORY . '/offline.json';
    public const DEFAULT_MESSAGE = 'We are updating our website. Please check back soon.';
    public const REDIRECT_TO_FRONTEND = 'redirect';

    private WriteInterface $directory;

    public function __construct(Filesystem $filesystem)
    {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function isOffline(): bool
    {
        return $this->directory->isFile(self::STATE_FILE);
    }

    public function getState(): array
    {
        if (!$this->isOffline()) {
            return [];
        }

        try {
            $state = json_decode($this->directory->readFile(self::STATE_FILE), true, 8, JSON_THROW_ON_ERROR);
            return is_array($state) ? $state : [];
        } catch (\Magento\Framework\Exception\FileSystemException | \JsonException $exception) {
            return [];
        }
    }

    public function getMessage(): string
    {
        return (string) ($this->getState()['message'] ?? self::DEFAULT_MESSAGE);
    }

    public function redirectsAdministrators(): bool
    {
        return ($this->getState()['admin_access'] ?? '') === self::REDIRECT_TO_FRONTEND;
    }

    public function takeOffline(string $message, int $adminId, bool $redirectAdministrators = false): void
    {
        $message = trim($message);
        if (mb_strlen($message) > 500) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The offline message must be 500 characters or fewer.'));
        }

        $current = $this->getState();
        $currentlyRedirects = ($current['admin_access'] ?? '') === self::REDIRECT_TO_FRONTEND;
        $sameAccessMode = $currentlyRedirects === $redirectAdministrators;
        $state = [
            'id' => $sameAccessMode ? ($current['id'] ?? bin2hex(random_bytes(16))) : bin2hex(random_bytes(16)),
            'admin_access' => $redirectAdministrators ? self::REDIRECT_TO_FRONTEND : 'preview',
            'message' => $message !== '' ? $message : self::DEFAULT_MESSAGE,
            'updated_at' => gmdate(DATE_ATOM),
            'admin_id' => $adminId,
        ];

        $this->directory->create(self::DIRECTORY);
        $temporary = self::STATE_FILE . '.' . bin2hex(random_bytes(8));
        $this->directory->writeFile($temporary, json_encode($state, JSON_THROW_ON_ERROR));
        $this->directory->changePermissions($temporary, 0640);
        $this->directory->renameFile($temporary, self::STATE_FILE);
    }

    public function putOnline(): void
    {
        if ($this->isOffline()) {
            $this->directory->delete(self::STATE_FILE);
        }
    }
}
