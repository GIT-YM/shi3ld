<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Plugin;

use Magento\Backend\Model\Auth;
use Shi3ld\Blog\Model\Editorial\Presence;
use Shi3ld\Content\Model\Editor\Documents;

class EditorialDocuments
{
    public function __construct(private Presence $presence, private Auth $auth) {}

    public function beforeSave(Documents $subject, string $type, int $id, array $payload, int $version, string $author, string $action = 'draft', ?string $liveHash = null): void
    {
        if (in_array($type, ['article', 'blog_locale'], true) && ($user = $this->auth->getUser())) {
            $this->presence->assertEditor($id, (int) $user->getId(), $type);
        }
    }

    public function beforePublish(Documents $subject, string $type, int $id, int $version, string $author): void
    {
        if (in_array($type, ['article', 'blog_locale'], true) && ($user = $this->auth->getUser())) {
            $this->presence->assertEditor($id, (int) $user->getId(), $type);
        }
    }
}
