<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Editorial\Frontend;
use Shi3ld\Blog\Model\Editorial\Metadata;
use Shi3ld\Blog\Model\Editorial\Taxonomy;
use Shi3ld\Blog\Model\Editorial\Translations;
use Shi3ld\Blog\Model\PageContext;
use Shi3ld\Blog\Model\Presentation;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;
use Shi3ld\Content\Model\Editor\Preview;

abstract class Page implements HttpGetActionInterface
{
    public function __construct(protected Http $request, protected PageFactory $pages, protected ForwardFactory $forwards,
        protected RedirectFactory $redirects, protected Repository $posts, protected PageContext $context,
        protected Urls $urls, protected Preview $preview, protected Presentation $presentation,
        protected StoreManagerInterface $stores,
        protected Translations $translations, protected Taxonomy $taxonomy,
        protected Metadata $editorial, protected Frontend $features) {}

    protected function render(string $type)
    {
        $title = 'Advice & resources';
        $description = 'Articles and practical ideas from Shi3ld Alliance about accessibility, cleaning and caring for your spaces.';
        $path = 'blog';
        $options = [];
        $base = null;
        try {
            if ($type === 'post') {
                $base = $this->posts->post((int) $this->request->getParam('id'));
                $post = $this->translations->localized('post', $base);
                $draft = $this->preview->get();
                $private = $draft && $draft['type'] === 'article' && (int) $draft['id'] === (int) $base['post_id'];
                if ($private) {
                    $payload = $draft['payload'];
                    $post = array_replace($post, $payload, ['content' => $payload['html']]);
                    if (isset($payload['editorial'])) {
                        $post['editorial'] = json_encode($payload['editorial'], JSON_THROW_ON_ERROR);
                    }
                }
                if ($translated = $this->translations->preview($base, $draft)) {
                    $post = $translated;
                    $private = true;
                }
                if (! $private && (! (int) $base['is_published'] || ! $base['published_at'] || $base['published_at'] > gmdate('Y-m-d H:i:s'))) {
                    return $this->forwards->create()->forward('noroute');
                }
                $this->context->preview = (bool) $private;
                $this->context->post = $post;
                $path = $this->urls->path('post', $post['slug']);
                $title = $post['meta_title'] ?: $post['title'];
                $description = $post['meta_description'] ?: $this->presentation->excerpt($post, 160);
            } else {
                if ($type === 'category') {
                    $base = $this->posts->category((int) $this->request->getParam('id'));
                    if (! (int) $base['is_active']) {
                        return $this->forwards->create()->forward('noroute');
                    }
                    $category = $this->translations->localized('category', $base);
                    $this->context->category = $category;
                    $path = $this->urls->path('category', $category['slug']);
                    $title = $category['meta_title'] ?: $category['title'].' | Advice & resources';
                    $description = $category['meta_description'] ?: $category['description'];
                    $options['category_ids'] = [(int) $category['category_id']];
                } elseif ($type === 'author') {
                    $author = $this->taxonomy->get('author', (int) $this->request->getParam('id'));
                    if (! (int) $author['is_active']) {
                        return $this->forwards->create()->forward('noroute');
                    }
                    $this->context->author = $author;
                    $options['author_id'] = (int) $author['author_id'];
                    $path = $this->urls->path('author', $author['slug']);
                    $title = $author['name'].' | Authors';
                    $description = $author['biography'];
                } elseif ($type === 'tag') {
                    $tag = $this->taxonomy->get('tag', (int) $this->request->getParam('id'));
                    $this->context->tag = $tag;
                    $options['tag_ids'] = [(int) $tag['tag_id']];
                    $path = $this->urls->path('tag', $tag['slug']);
                    $title = $tag['name'].' | Topics';
                }
                $query = $this->request->getParam('q', '');
                $this->context->search = is_string($query) ? mb_substr(trim($query), 0, 100) : '';
                $options['search'] = $this->context->search;
                $filters = [];
                foreach (['c' => 'category_ids', 'a' => 'author_id', 't' => 'tag_ids'] as $parameter => $option) {
                    $value = $this->request->getParam($parameter, '0');
                    if (! is_scalar($value) || ! ctype_digit((string) $value)) {
                        throw new \InvalidArgumentException('Choose valid article filters.');
                    }
                    if ((int) $value > 0) {
                        $filters[$parameter] = (int) $value;
                        $options[$option] = $option === 'author_id' ? (int) $value : [(int) $value];
                    }
                }
                foreach (['from', 'to'] as $date) {
                    $value = $this->request->getParam($date, '');
                    if ($value !== '') {
                        $filters[$date] = $this->editorial->date($value);
                        $options[$date] = $filters[$date];
                    }
                }
                if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
                    throw new \InvalidArgumentException('The end date must follow the start date.');
                }
                $sort = $this->request->getParam('sort', 'newest');
                if (! in_array($sort, ['newest', 'oldest', 'relevance'], true)) {
                    throw new \InvalidArgumentException('Choose a valid article order.');
                }$options['sort'] = $sort;
                if ($sort !== 'newest') {
                    $filters['sort'] = $sort;
                }
                $this->context->filters = $filters;
                $number = $this->request->getParam('p', '1');
                if (! is_scalar($number) || ! ctype_digit((string) $number) || (int) $number < 1 || (int) $number > 100000) {
                    return $this->forwards->create()->forward('noroute');
                }
                $this->context->page = (int) $number;
                $this->context->listing = $this->posts->listing($options, $this->context->page, 12);
                if ($this->context->page > max(1, (int) ceil($this->context->listing['total'] / 12))) {
                    return $this->forwards->create()->forward('noroute');
                }
            }
        } catch (NoSuchEntityException|\InvalidArgumentException) {
            return $this->forwards->create()->forward('noroute');
        }
        $query = $this->context->filters;
        if ($this->context->page > 1) {
            $query['p'] = $this->context->page;
            $title .= ' — Page '.$this->context->page;
        }if ($this->context->search !== '') {
            $query['q'] = $this->context->search;
        }
        $actual = $this->stores->getStore()->getUrl('', ['_direct' => $path, '_query' => $query, '_nosid' => true]);
        $this->context->canonical = $actual;
        $alias = $this->request->getAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS);
        if (! $this->context->preview && (($type !== 'index' && $alias !== $path) || ($type === 'index' && rtrim($this->request->getPathInfo(), '/') !== '/blog'))) {
            return $this->redirects->create()->setUrl($actual)->setHttpResponseCode(301);
        }
        if ($type === 'post') {
            $meta = $this->editorial->get($this->context->post);
            if ($meta['canonical'] !== '') {
                $canonical = $meta['canonical'];
                if (str_starts_with($canonical, '/')) {
                    $baseUrl = $this->stores->getStore()->getBaseUrl();
                    $canonical = parse_url($baseUrl, PHP_URL_SCHEME).'://'.parse_url($baseUrl, PHP_URL_HOST).(parse_url($baseUrl, PHP_URL_PORT) ? ':'.parse_url($baseUrl, PHP_URL_PORT) : '').$canonical;
                }$this->context->canonical = $canonical;
            }
        }
        if ($base && in_array($type, ['post', 'category'], true) && ! $this->context->preview && (int) $this->stores->getStore()->getId() !== (int) $this->stores->getDefaultStoreView()->getId()) {
            $localized = $type === 'post' ? $this->context->post : $this->context->category;
            if (empty($localized['translation_store_id']) && ($type !== 'post' || $this->editorial->get($this->context->post)['canonical'] === '')) {
                $this->context->canonical = $this->urls->url($type, $base['slug'], (int) $this->stores->getDefaultStoreView()->getId());
            }
        }
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->set($title);
        $page->getConfig()->setDescription(mb_substr(strip_tags((string) $description), 0, 1000));
        $page->getConfig()->addRemotePageAsset($this->context->canonical, 'canonical', ['attributes' => ['rel' => 'canonical']], 'shi3ld_blog_canonical');
        $page->getConfig()->addRss('Shi3ld article updates', $this->stores->getStore()->getUrl('', ['_direct' => 'blog/rss', '_nosid' => true]));
        if ($base && in_array($type, ['post', 'category'], true) && ! $this->context->preview) {
            foreach ($this->features->alternateLinks($type, $base) as $language => $url) {
                $page->getConfig()->addRemotePageAsset($url, 'link_rel', ['attributes' => ['rel' => 'alternate', 'hreflang' => $language]], 'shi3ld_blog_language_'.$language);
            }
        }
        if ($type === 'post') {
            $post = $this->context->post;
            $meta = $this->editorial->get($post);
            $sharing = ['og:type' => 'article', 'og:title' => $meta['social_title'] ?: $post['title'], 'og:description' => $meta['social_description'] ?: $description, 'og:url' => $this->context->canonical, 'og:image' => $this->presentation->image($meta['social_image'] ?: $post['featured_image'], 1280), 'twitter:card' => 'summary_large_image'];
            foreach ($sharing as $name => $value) {
                $page->getConfig()->setMetadata($name, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
            }
        }
        if ($this->context->preview || $this->context->search !== '' || $this->context->filters !== []) {
            $page->getConfig()->setRobots('NOINDEX,FOLLOW');
        }

        return $page;
    }
}
