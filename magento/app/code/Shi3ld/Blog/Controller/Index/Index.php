<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Index;

use Shi3ld\Blog\Controller\Page;

class Index extends Page
{
    public function execute()
    {
        return $this->render('index');
    }
}
