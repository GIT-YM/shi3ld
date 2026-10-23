<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Plugin;

use Shi3ld\Blog\Model\PageContext;
use Shi3ld\Content\Block\EditorToolbar;

class Toolbar
{
    public function __construct(private PageContext $page) {}

    public function afterGetLocateUrl(EditorToolbar $subject, string $url): string
    {
        return $this->page->post ? $url.'&article_id='.(int) $this->page->post['post_id'] : $url;
    }
}
