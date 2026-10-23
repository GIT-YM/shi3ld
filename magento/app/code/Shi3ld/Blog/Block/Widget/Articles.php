<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Block\Widget;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Shi3ld\Blog\Model\Editorial\Translations;
use Shi3ld\Blog\Model\Presentation;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;
use Shi3ld\Blog\Model\WidgetOptions;
use Shi3ld\Content\Model\Builder\Runtime;

class Articles extends Template implements BlockInterface, IdentityInterface
{
    protected $_template = 'Shi3ld_Blog::widget/articles.phtml';

    private ?array $normalized = null;

    private ?array $articles = null;

    public function __construct(Template\Context $context, private Repository $posts, private WidgetOptions $settings,
        private Presentation $presentation, private Urls $urls, private Runtime $builder, private Translations $translations, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function options(): array
    {
        return $this->normalized ??= $this->settings->normalize($this->getData());
    }

    public function configurePublic(array $input): self
    {
        $this->setData($this->settings->normalize($input));
        $this->normalized = null;
        $this->articles = null;

        return $this;
    }

    public function articles(): array
    {
        if (is_array($this->getData('preloaded_articles'))) {
            return $this->getData('preloaded_articles');
        }
        if ($this->articles !== null) {
            return $this->articles;
        }
        $options = $this->options();
        $options['category_ids'] = $options['categories'] === '' ? [] : explode(',', $options['categories']);
        unset($options['categories']);
        if ($this->getData('exclude')) {
            $options['exclude'] = (int) $this->getData('exclude');
        }

        return $this->articles = $this->posts->listing($options, 1, $options['limit'])['items'];
    }

    public function categories(array $post): array
    {
        $rows = isset($post['category_ids']) ? array_values(array_filter($this->posts->categories(), fn ($row) => in_array((int) $row['category_id'], array_map('intval', $post['category_ids']), true))) : $this->posts->assignedCategories((int) $post['post_id']);

        return array_map(fn ($row) => $this->translations->localized('category', $row), $rows);
    }

    public function urlFor(array $post): string
    {
        return $this->urls->url('post', $post['slug']);
    }

    public function categoryUrl(array $category): string
    {
        return $this->urls->url('category', $category['slug']);
    }

    public function excerpt(array $post): string
    {
        return $this->presentation->excerpt($post, $this->options()['excerpt_length']);
    }

    public function image(array $post): string
    {
        return $this->presentation->image($post['featured_image'], 800);
    }

    public function srcset(array $post): string
    {
        return $this->presentation->srcset($post['featured_image']);
    }

    public function imageSizes(): string
    {
        $options = $this->options();
        if ($options['design'] === 'list') {
            return '100vw';
        }
        $mobile = (int) ceil(100 / $options['mobile_columns']);
        $tablet = (int) ceil(100 / $options['tablet_columns']);
        $desktop = (int) ceil(100 / $options['columns']);
        if ($options['design'] === 'featured-grid') {
            $desktop = min(100, $desktop * 2);
        }

        return '(max-width: 640px) '.$mobile.'vw, (max-width: 1024px) '.$tablet.'vw, '.$desktop.'vw';
    }

    public function viewAllUrl(): string
    {
        $ids = array_filter(explode(',', $this->options()['categories']));
        if (count($ids) === 1) {
            foreach ($this->posts->categories() as $category) {
                if ((string) $category['category_id'] === (string) reset($ids)) {
                    return $this->categoryUrl($category);
                }
            }
        }

        return $this->getUrl('', ['_direct' => 'blog']);
    }

    public function getIdentities(): array
    {
        return ['shi3ld_blog'];
    }

    public function customCard(array $post): string
    {
        $key = $this->options()['card_template'];
        if (! $key || ! ($asset = $this->builder->lookup($key)) || ($asset['builder']['asset']['kind'] ?? '') !== 'card') {
            return '';
        }

        return $this->builder->wrap($asset['html'], $asset['builder'], ['key' => 'card-'.$key.'-'.$post['post_id'], 'fields' => ['title' => $post['title'],
            'excerpt' => $this->excerpt($post), 'image' => $this->image($post), 'url' => $this->urlFor($post), 'author' => $post['author'],
            'category' => implode(', ', array_column($this->categories($post), 'title')), 'date' => gmdate('j F Y', strtotime($post['published_at'].' UTC'))]]);
    }
}
