<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Category;

use Shi3ld\Blog\Controller\Page;

class View extends Page
{
    public function execute()
    {
        return $this->render('category');
    }
}
