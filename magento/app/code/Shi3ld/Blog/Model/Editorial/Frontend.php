<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;
use Shi3ld\Content\Model\Builder\Products;

class Frontend
{
    private array $reading = [];

    public function __construct(private Metadata $metadata, private ArticleText $text, private Taxonomy $taxonomy, private Translations $translations,
        private Repository $posts, private Urls $urls, private Tokens $tokens,
        private Products $products, private Escaper $escaper,
        private StoreManagerInterface $stores, private ScopeConfigInterface $config) {}

    public function metadata(array $post): array
    {
        return $this->metadata->get($post);
    }

    public function reading(array $post, string $html): array
    {
        $key = $post['post_id'].':'.hash('sha256', $html);

        return $this->reading[$key] ??= $this->text->analyse($html, (int) $post['post_id']);
    }

    public function body(array $post, string $html): string
    {
        $analysis = $this->reading($post, $html);
        $metadata = $this->metadata($post);
        $toc = '';
        if ($metadata['show_toc'] && count($analysis['headings']) >= 2) {
            $toc = '<nav class="blog-toc" aria-label="On this page"><h2>On this page</h2><ol>';
            foreach ($analysis['headings'] as $heading) {
                $toc .= '<li class="level-'.$heading['level'].'"><a href="#'.$this->escaper->escapeHtmlAttr($heading['id']).'">'.$this->escaper->escapeHtml($heading['text']).'</a></li>';
            }$toc .= '</ol></nav>';
        }

        return $toc.$analysis['html'];
    }

    public function author(array $post): ?array
    {
        $id = $this->metadata($post)['author_id'];
        if (! $id) {
            return null;
        }try {
            $author = $this->taxonomy->get('author', $id);

            return $author['is_active'] ? $author : null;
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    public function tags(array $post): array
    {
        $ids = $this->metadata($post)['tag_ids'];

        return array_values(array_filter($this->taxonomy->all('tag'), fn ($tag) => in_array((int) $tag['tag_id'], $ids, true)));
    }

    public function related(array $post): array
    {
        $meta = $this->metadata($post);
        $exclude = array_values(array_unique(array_merge([(int) $post['post_id']], $meta['excluded_ids'])));
        $items = [];
        foreach ([['category_ids' => $post['category_ids'] ?? []], ['tag_ids' => $meta['tag_ids']]] as $options) {
            if (! array_filter($options)) {
                continue;
            }foreach ($this->posts->listing($options + ['exclude' => $exclude], 1, 20)['items'] as $item) {
                $items[(int) $item['post_id']] = $item;
            }
        }
        if (! $items) {
            foreach ($this->posts->listing(['exclude' => $exclude], 1, 20)['items'] as $item) {
                $items[(int) $item['post_id']] = $item;
            }
        }
        if ($meta['pinned_ids']) {
            foreach ($this->posts->listing(['sort' => 'manual', 'articles' => $meta['pinned_ids'], 'exclude' => $exclude], 1, 100)['items'] as $item) {
                $items[(int) $item['post_id']] = $item;
            }
        }
        $rank = array_flip($meta['pinned_ids']);
        usort($items, function ($a, $b) use ($rank, $meta) {
            $aRank = $rank[(int) $a['post_id']] ?? 10000;
            $bRank = $rank[(int) $b['post_id']] ?? 10000;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }$aScore = count(array_intersect($this->metadata($a)['tag_ids'], $meta['tag_ids']));
            $bScore = count(array_intersect($this->metadata($b)['tag_ids'], $meta['tag_ids']));

            return $bScore <=> $aScore ?: strcmp((string) $b['published_at'], (string) $a['published_at']);
        });

        return array_slice($items, 0, 3);
    }

    public function products(array $post): string
    {
        $skus = $this->metadata($post)['product_skus'];

        return $skus ? '<section class="blog-connected" data-blog-products><h2>Products mentioned in this article</h2>'.$this->products->html(count($skus), $skus).'</section>' : '';
    }

    public function attribution(array $post, bool $tracking = true): string
    {
        return $tracking ? $this->tokens->issue('attribution', (int) $post['post_id'], 604800) : '';
    }

    public function services(array $post, bool $tracking = true): array
    {
        $token = $this->attribution($post, $tracking);
        $result = [];
        foreach ($this->metadata($post)['services'] as $service) {
            if ($tracking) {
                $service['url'] .= '?'.http_build_query(['blog_ref' => (int) $post['post_id'], 'blog_token' => $token]);
            }$result[] = $service;
        }

        return $result;
    }

    public function alternateLinks(string $type, array $base): array
    {
        $result = [];
        $default = (int) $this->stores->getDefaultStoreView()->getId();
        $published = [];
        foreach ($this->translations->all($type, (int) $base[$type.'_id']) as $row) {
            if ($row['is_published']) {
                $published[(int) $row['store_id']] = true;
            }
        }
        foreach ($this->stores->getStores() as $store) {
            if (! $store->isActive() || ((int) $store->getId() !== $default && ! isset($published[(int) $store->getId()]))) {
                continue;
            }$row = $this->translations->localized($type, $base, (int) $store->getId());
            $locale = strtolower(str_replace('_', '-', (string) $this->config->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, (int) $store->getId())));
            $url = $this->urls->url($type, $row['slug'], (int) $store->getId());
            if ($locale !== '' && ! isset($result[$locale]) && ! in_array($url, $result, true)) {
                $result[$locale] = $url;
            }
        }
        $result['x-default'] = $this->urls->url($type, $base['slug'], (int) $this->stores->getDefaultStoreView()->getId());

        return $result;
    }
}
