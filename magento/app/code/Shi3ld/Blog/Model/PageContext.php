<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

/** Request-scoped data shared by controllers and their view blocks. */
class PageContext
{
    public ?array $post = null;

    public ?array $category = null;

    public ?array $author = null;

    public ?array $tag = null;

    public array $filters = [];

    public array $listing = ['items' => [], 'total' => 0];

    public int $page = 1;

    public string $search = '';

    public string $canonical = '';

    public bool $preview = false;
}
