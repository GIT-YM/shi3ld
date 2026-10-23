<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Security\Model\AdminSessionInfoFactory;
use Magento\Security\Model\ResourceModel\AdminSessionInfo as SessionResource;
use Magento\User\Model\UserFactory;
use Magento\User\Model\ResourceModel\User as UserResource;

class Preview
{
    private const COOKIE = 'shi3ld_cms_preview';
    private const DIRECTORY = 'shi3ld/cms-previews';
    private ?array $current = null;
    private bool $loaded = false;

    public function __construct(
        private Filesystem $filesystem,
        private CookieManagerInterface $cookies,
        private CookieMetadataFactory $metadata,
        private Http $request,
        private AdminSessionInfoFactory $sessions,
        private SessionResource $sessionResource,
        private UserFactory $users,
        private UserResource $userResource,
        private \Magento\Framework\Acl\Builder $acl
    ) {}

    public function start(array $document, \Magento\User\Model\User $user, int $sessionId): string
    {
        $this->end();
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create(self::DIRECTORY);
        $directory->changePermissions(self::DIRECTORY, 0700);
        foreach ($directory->read(self::DIRECTORY) as $path) {
            if ($directory->isFile($path) && preg_match('/\A[a-f0-9]{64}\.json\z/D', basename($path)) && (int) ($directory->stat($path)['mtime'] ?? time()) < time() - 900) {
                $directory->delete($path);
            }
        }
        $token = bin2hex(random_bytes(32));
        $id = bin2hex(random_bytes(16));
        $document += ['preview_id' => $id, 'expires_at' => time() + 900, 'admin_id' => (int) $user->getId(),
            'admin_session_id' => $sessionId, 'password_version' => hash('sha256', (string) $user->getPassword())];
        $path = self::DIRECTORY.'/'.hash('sha256', $token).'.json';
        $directory->writeFile($path, json_encode($document, JSON_THROW_ON_ERROR));
        $directory->changePermissions($path, 0600);
        $this->cookies->setSensitiveCookie(self::COOKIE, $token, $this->metadata->createSensitiveCookieMetadata([
            'path' => '/', 'domain' => '', 'secure' => true, 'http_only' => true, 'samesite' => 'Lax', 'duration' => 900,
        ]));
        return $id;
    }

    public function get(): ?array
    {
        if ($this->loaded) {
            return $this->current;
        }
        $this->loaded = true;
        $id = $this->request->getParam('shi3ld_preview');
        $token = $this->cookies->getCookie(self::COOKIE);
        if (!is_string($id) || !preg_match('/\A[a-f0-9]{32}\z/D', $id)
            || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return null;
        }
        try {
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            $path = self::DIRECTORY.'/'.hash('sha256', $token).'.json';
            if (!$directory->isFile($path)) {
                return null;
            }
            $data = json_decode($directory->readFile($path), true, 32, JSON_THROW_ON_ERROR);
            if ((int) ($data['expires_at'] ?? 0) <= time() || !hash_equals((string) ($data['preview_id'] ?? ''), $id)) {
                return null;
            }
            $session = $this->sessions->create();
            $this->sessionResource->load($session, (int) ($data['admin_session_id'] ?? 0));
            $user = $this->users->create();
            $this->userResource->load($user, (int) ($data['admin_id'] ?? 0));
            if (!$session->getId() || !$session->isLoggedInStatus() || (int) $session->getUserId() !== (int) $user->getId()
                || !$user->getId() || (int) $user->getIsActive() !== 1
                || !hash_equals((string) ($data['password_version'] ?? ''), hash('sha256', (string) $user->getPassword()))
                || !$this->acl->getAcl()->isAllowed($user->getAclRole(), 'Shi3ld_Content::visual_edit')) {
                return null;
            }
            return $this->current = $data;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function end(): void
    {
        $token = $this->cookies->getCookie(self::COOKIE);
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            $path = self::DIRECTORY.'/'.hash('sha256', $token).'.json';
            if ($directory->isFile($path)) {
                $directory->delete($path);
            }
        }
        $this->cookies->deleteCookie(self::COOKIE, $this->metadata->createCookieMetadata(['path' => '/', 'domain' => '', 'secure' => true, 'http_only' => true, 'samesite' => 'Lax']));
    }
}
