<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Shi3ld\Blog\Block\Widget\Articles;
use Shi3ld\Blog\Model\Editorial\Frontend;
use Shi3ld\Blog\Model\Editorial\Taxonomy;
use Shi3ld\Blog\Model\Editorial\Translations;
use Shi3ld\Blog\Model\PageContext;
use Shi3ld\Blog\Model\Presentation;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;
use Shi3ld\Content\Model\Editor\Renderer;

class Blog extends Template implements IdentityInterface
{
    public function __construct(Template\Context $context, private PageContext $page, private Repository $posts,
        private Urls $urls, private Presentation $presentation, private Renderer $renderer,
        private Frontend $features, private Taxonomy $taxonomy,
        private Translations $translations, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function page(): PageContext
    {
        return $this->page;
    }

    public function articles(): array
    {
        return $this->page->listing['items'];
    }

    public function categories(): array
    {
        return array_map(fn ($row) => $this->translations->localized('category', $row), $this->posts->categories());
    }

    public function authors(): array
    {
        return $this->taxonomy->all('author', true);
    }

    public function tags(): array
    {
        return $this->taxonomy->all('tag');
    }

    public function topicUrl(string $type, array $row): string
    {
        return $this->urls->url($type, $row['slug']);
    }

    public function metadata(array $post): array
    {
        return $this->features->metadata($post);
    }

    public function authorProfile(array $post): ?array
    {
        return $this->features->author($post);
    }

    public function postTags(array $post): array
    {
        return $this->features->tags($post);
    }

    public function reading(array $post): array
    {
        return $this->features->reading($post, $this->rendered($post));
    }

    public function productLinks(array $post): string
    {
        return $this->features->products($post);
    }

    public function serviceLinks(array $post): array
    {
        return $this->features->services($post, ! $this->page->preview);
    }

    public function attribution(array $post): string
    {
        return $this->features->attribution($post, ! $this->page->preview);
    }

    public function subscription(): string
    {
        return $this->getLayout()->createBlock(Template::class)->setTemplate('Shi3ld_Blog::subscription.phtml')->toHtml();
    }

    public function urlFor(array $post): string
    {
        return $this->urls->url('post', $post['slug']);
    }

    public function categoryUrl(array $category): string
    {
        return $this->urls->url('category', $category['slug']);
    }

    public function excerpt(array $post, int $length = 150): string
    {
        return $this->presentation->excerpt($post, $length);
    }

    public function image(array $post, int $width = 640): string
    {
        return $this->presentation->image($post['featured_image'], $width);
    }

    public function srcset(array $post): string
    {
        return $this->presentation->srcset($post['featured_image']);
    }

    private array $rendered = [];

    private function rendered(array $post): string
    {
        return $this->rendered[(int) $post['post_id']] ??= $this->renderer->render($post['content']);
    }

    public function body(array $post): string
    {
        return $this->features->body($post, $this->rendered($post));
    }

    public function cards(): string
    {
        return $this->getLayout()->createBlock(Articles::class)->setData([
            'preloaded_articles' => $this->articles(), 'heading' => '', 'view_all' => 0, 'columns' => 3, 'limit' => 12,
        ])->toHtml();
    }

    public function assignedCategories(array $post): array
    {
        return array_values(array_filter($this->categories(), fn ($row) => in_array((int) $row['category_id'], array_map('intval', $post['category_ids'] ?? []), true)));
    }

    public function pageUrl(int $number): string
    {
        $path = $this->page->category ? $this->urls->path('category', $this->page->category['slug']) : ($this->page->author ? $this->urls->path('author', $this->page->author['slug']) : ($this->page->tag ? $this->urls->path('tag', $this->page->tag['slug']) : 'blog'));

        return $this->getUrl('', ['_direct' => $path,
            '_query' => array_filter($this->page->filters + ['p' => $number > 1 ? $number : null, 'q' => $this->page->search ?: null])]);
    }

    public function related(array $post): string
    {
        return $this->getLayout()->createBlock(Articles::class)->setData([
            'heading' => 'Related articles', 'preloaded_articles' => $this->features->related($post), 'limit' => 3,
            'columns' => 3, 'exclude' => (int) $post['post_id'], 'view_all' => 1,
        ])->toHtml();
    }

    public function structuredData(): array
    {
        if (! $this->page->post || $this->page->preview) {
            return [];
        }
        $post = $this->page->post;
        $data = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'],
            'mainEntityOfPage' => $this->page->canonical, 'url' => $this->page->canonical,
            'datePublished' => gmdate('c', strtotime($post['published_at'].' UTC')), 'dateModified' => gmdate('c', strtotime($post['updated_at'].' UTC')),
            'description' => $this->excerpt($post, 300), 'publisher' => ['@type' => 'Organization', 'name' => 'Shi3ld Alliance']];
        if ($post['author'] !== '') {
            $data['author'] = ['@type' => 'Person', 'name' => $post['author']];
        }
        if ($author = $this->features->author($post)) {
            $data['author'] = ['@type' => 'Person', 'name' => $author['name'], 'url' => $this->urls->url('author', $author['slug'])];
        }
        if ($post['featured_image'] !== '') {
            $data['image'] = $this->image($post, 1280);
        }

        return $data;
    }

    public function getIdentities(): array
    {
        return ['shi3ld_blog'];
    }

    public function profileData(): array
    {
        $author = $this->page->author;
        if (! $author) {
            return [];
        }

        return ['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'mainEntity' => ['@type' => 'Person', 'name' => $author['name'], 'url' => $this->urls->url('author', $author['slug']), 'description' => $author['biography']]];
    }
}
