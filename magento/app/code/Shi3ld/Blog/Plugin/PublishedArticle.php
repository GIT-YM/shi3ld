<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Plugin;

use Shi3ld\Blog\Model\Lifecycle;
use Shi3ld\Content\Model\Editor\Documents;

class PublishedArticle
{
    public function __construct(private Lifecycle $lifecycle) {}

    public function afterPublish(Documents $subject, array $result, string $type, int $id, int $version, string $author): array
    {
        if (in_array($type, ['article', 'blog_locale', 'cat_locale'], true)) {
            $this->lifecycle->refresh();
        }

        return $result;
    }
}
