<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model;

class BuilderContext
{
    public function __construct(private PageContext $page, private Presentation $presentation, private Urls $urls) {}

    public function context(): array
    {
        if ($post = $this->page->post) {
            return ['key' => 'article-'.$post['post_id'], 'kind' => 'article', 'fields' => ['title' => $post['title'], 'excerpt' => $this->presentation->excerpt($post, 300),
                'image' => $this->presentation->image($post['featured_image'], 800), 'url' => $this->urls->url('post', $post['slug']), 'author' => $post['author'],
                'date' => $post['published_at'] ? gmdate('j F Y', strtotime($post['published_at'].' UTC')) : '']];
        }
        if ($category = $this->page->category) {
            return ['key' => 'blog-category-'.$category['category_id'], 'kind' => 'category', 'fields' => ['title' => $category['title'], 'excerpt' => $category['description']]];
        }

        return [];
    }
}
