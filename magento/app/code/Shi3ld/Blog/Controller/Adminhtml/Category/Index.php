<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Category;

class Index extends \Shi3ld\Blog\Controller\Adminhtml\Article\Index
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_publish';

    protected string $screen = 'categories';

    protected string $heading = 'Blog Categories';
}
