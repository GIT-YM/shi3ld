<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Reader;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Shi3ld\Blog\Model\Editorial\Engagement;
use Shi3ld\Blog\Model\Editorial\Guard;
use Shi3ld\Blog\Model\Editorial\Metadata;
use Shi3ld\Blog\Model\Editorial\Settings;
use Shi3ld\Blog\Model\Editorial\Tokens;
use Shi3ld\Blog\Model\Editorial\Translations;
use Shi3ld\Blog\Model\Repository;

class Context implements HttpGetActionInterface
{
    public function __construct(private Http $request, private JsonFactory $results,
        private FormKey $formKey, private Repository $posts,
        private Metadata $metadata, private Settings $settings,
        private Engagement $engagement, private Tokens $tokens,
        private Guard $guard, private Translations $translations) {}

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true)->setHeader('X-Robots-Tag', 'noindex', true);
        try {
            $this->guard->limit('reader-context', 120, 60);
            $id = (int) $this->request->getParam('post_id', 0);
            $post = null;
            if ($id) {
                $post = $this->posts->post($id);
                if (! $post['is_published'] || ! $post['published_at'] || $post['published_at'] > gmdate('Y-m-d H:i:s')) {
                    throw new \InvalidArgumentException('This article is not currently available.');
                }$post = $this->translations->localized('post', $post);
            }
            $settings = $this->settings->get();
            $comments = $post && $settings['comments_enabled'] && $this->metadata->get($post)['allow_comments'];
            $categories = array_map(fn ($row) => ['id' => (int) $row['category_id'], 'title' => $this->translations->localized('category', $row)['title']], $this->posts->categories());

            return $result->setData(['csrf' => $this->formKey->getFormKey(), 'nonce' => $this->tokens->issue('reader', $id), 'comments_enabled' => (bool) $comments,
                'comments' => $comments ? $this->engagement->comments($id) : [], 'subscriptions_enabled' => $settings['subscriptions_enabled'] && $settings['delivery_enabled'],
                'categories' => $categories, 'stats' => $post ? $this->engagement->summary($id) : []]);
        } catch (NoSuchEntityException|\InvalidArgumentException $exception) {
            return $result->setHttpResponseCode(404)->setData(['error' => $exception->getMessage()]);
        }
    }
}
