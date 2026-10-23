<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Tag;

use Shi3ld\Blog\Controller\Page;

class View extends Page
{
    public function execute()
    {
        return $this->render('tag');
    }
}
