<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Plugin;

use Shi3ld\Blog\Block\Blog;
use Shi3ld\Content\Model\Builder\Runtime;

class BuilderTemplates
{
    public function __construct(private Runtime $runtime) {}

    public function afterToHtml(Blog $block, string $html): string
    {
        if ($post = $block->page()->post) {
            $context = ['key' => 'article-'.$post['post_id'], 'editing' => $block->page()->preview, 'fields' => ['category' => implode(', ', array_column($block->assignedCategories($post), 'title'))]];
            $html = $this->runtime->wrap($html, $post['builder'] ?? $this->runtime->settings('article', (int) $post['post_id']), $context);

            return $this->runtime->template('article', $html, $context);
        }

        return $this->runtime->template('category', $html, ['kind' => 'category']);
    }
}
