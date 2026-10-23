<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadata;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Security\Model\AdminSessionInfoFactory;
use Magento\Security\Model\ResourceModel\AdminSessionInfo as AdminSessionResource;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

class PreviewSession
{
    public const COOKIE_NAME = 'shi3ld_site_preview';
    public const LIFETIME = 3600;
    private const DIRECTORY = SiteAccess::DIRECTORY . '/sessions';

    private WriteInterface $directory;

    public function __construct(
        Filesystem $filesystem,
        private SiteAccess $siteAccess,
        private CookieManagerInterface $cookies,
        private CookieMetadataFactory $cookieMetadata,
        private UserFactory $users,
        private UserResource $userResource,
        private AdminSessionInfoFactory $adminSessions,
        private AdminSessionResource $adminSessionResource
    ) {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function start(User $user, int $adminSessionId = 0): void
    {
        $state = $this->siteAccess->getState();
        if (empty($state['id'])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Take the site offline before starting a private preview.'));
        }
        if ($this->siteAccess->redirectsAdministrators() && !$this->isAdminSessionActive($adminSessionId, (int) $user->getId())) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Sign in to Magento Admin to view the offline website.'));
        }

        $this->end();
        $this->directory->create(self::DIRECTORY);
        $this->directory->changePermissions(self::DIRECTORY, 0700);
        $this->removeExpiredSessions();

        $token = bin2hex(random_bytes(32));
        $path = $this->getPath($token);
        $this->directory->writeFile($path, json_encode([
            'admin_id' => (int) $user->getId(),
            'admin_session_id' => $adminSessionId,
            'expires_at' => time() + self::LIFETIME,
            'offline_id' => $state['id'],
            'password_version' => hash('sha256', (string) $user->getPassword()),
        ], JSON_THROW_ON_ERROR));
        $this->directory->changePermissions($path, 0600);

        $metadata = $this->cookieMetadata->createSensitiveCookieMetadata([
            CookieMetadata::KEY_PATH => '/',
            CookieMetadata::KEY_DOMAIN => '',
            CookieMetadata::KEY_SECURE => true,
            CookieMetadata::KEY_HTTP_ONLY => true,
            CookieMetadata::KEY_SAME_SITE => 'Lax',
            CookieMetadata::KEY_DURATION => $this->siteAccess->redirectsAdministrators() ? null : self::LIFETIME,
        ]);
        $this->cookies->setSensitiveCookie(self::COOKIE_NAME, $token, $metadata);
    }

    public function isAllowed(): bool
    {
        $session = $this->readCurrentSession();
        $state = $this->siteAccess->getState();
        if (!$session || (int) ($session['expires_at'] ?? 0) <= time()
            || empty($state['id']) || !hash_equals((string) $state['id'], (string) ($session['offline_id'] ?? ''))
        ) {
            return false;
        }
        if ($this->siteAccess->redirectsAdministrators()
            && !$this->isAdminSessionActive((int) ($session['admin_session_id'] ?? 0), (int) ($session['admin_id'] ?? 0))
        ) {
            return false;
        }

        $user = $this->users->create();
        $this->userResource->load($user, (int) ($session['admin_id'] ?? 0));

        return $user->getId() && (int) $user->getIsActive() === 1
            && $user->hasAssigned2Role($user->getId())
            && hash_equals((string) ($session['password_version'] ?? ''), hash('sha256', (string) $user->getPassword()));
    }

    private function isAdminSessionActive(int $sessionId, int $adminId): bool
    {
        if ($sessionId <= 0 || $adminId <= 0) {
            return false;
        }

        $session = $this->adminSessions->create();
        $this->adminSessionResource->load($session, $sessionId);

        return $session->getId() && (int) $session->getUserId() === $adminId && $session->isLoggedInStatus();
    }

    public function end(): void
    {
        $token = $this->getToken();
        if ($token !== null && $this->directory->isFile($this->getPath($token))) {
            $this->directory->delete($this->getPath($token));
        }

        $this->cookies->deleteCookie(self::COOKIE_NAME, $this->cookieMetadata->createCookieMetadata([
            CookieMetadata::KEY_PATH => '/',
            CookieMetadata::KEY_DOMAIN => '',
            CookieMetadata::KEY_SECURE => true,
            CookieMetadata::KEY_HTTP_ONLY => true,
            CookieMetadata::KEY_SAME_SITE => 'Lax',
        ]));
    }

    private function getToken(): ?string
    {
        $token = $this->cookies->getCookie(self::COOKIE_NAME);
        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) ? $token : null;
    }

    private function getPath(string $token): string
    {
        return self::DIRECTORY . '/' . hash('sha256', $token) . '.json';
    }

    private function readCurrentSession(): ?array
    {
        $token = $this->getToken();
        return $token === null ? null : $this->readSession($this->getPath($token));
    }

    private function readSession(string $path): ?array
    {
        try {
            if (!$this->directory->isFile($path)) {
                return null;
            }
            $session = json_decode($this->directory->readFile($path), true, 8, JSON_THROW_ON_ERROR);
            return is_array($session) ? $session : null;
        } catch (\Magento\Framework\Exception\FileSystemException | \JsonException $exception) {
            return null;
        }
    }

    private function removeExpiredSessions(): void
    {
        foreach ($this->directory->read(self::DIRECTORY) as $path) {
            if (!$this->directory->isFile($path)) {
                continue;
            }
            $session = $this->readSession($path);
            if ((int) ($session['expires_at'] ?? 0) <= time()) {
                $this->directory->delete($path);
            }
        }
    }
}
