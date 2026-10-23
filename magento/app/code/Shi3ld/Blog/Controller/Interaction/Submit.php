<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Interaction;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Shi3ld\Blog\Model\Editorial\Engagement;
use Shi3ld\Blog\Model\Editorial\Guard;
use Shi3ld\Blog\Model\Editorial\Subscriptions;
use Shi3ld\Blog\Model\Editorial\Tokens;
use Shi3ld\Blog\Model\Editorial\Translations;
use Shi3ld\Blog\Model\Repository;

class Submit implements HttpPostActionInterface
{
    public function __construct(private Http $request, private JsonFactory $results,
        private Validator $csrf, private Repository $posts,
        private Tokens $tokens, private Guard $guard,
        private Engagement $engagement, private Subscriptions $subscriptions,
        private StoreManagerInterface $stores, private Translations $translations) {}

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        try {
            if (! $this->csrf->validate($this->request)) {
                return $result->setHttpResponseCode(403)->setData(['error' => 'Refresh the page before submitting again.']);
            }
            $data = (array) $this->request->getPostValue();
            $intent = $data['intent'] ?? '';
            $id = (int) ($data['post_id'] ?? 0);
            if (! is_string($data['nonce'] ?? null) || ! $this->tokens->verify('reader', $data['nonce'], $id, in_array($intent, ['comment', 'subscribe'], true) ? 3 : 0) || ! empty($data['website'])) {
                throw new \InvalidArgumentException('Please refresh the page, complete the form and try again.');
            }
            $this->guard->limit('reader-submit', 60, 60);
            if (in_array($intent, ['event', 'comment'], true)) {
                $post = $this->posts->post($id);
                if (! $post['is_published'] || ! $post['published_at'] || $post['published_at'] > gmdate('Y-m-d H:i:s')) {
                    throw new \InvalidArgumentException('This article is not currently available.');
                }
                if ($intent === 'comment') {
                    $this->engagement->comment($this->translations->localized('post', $post), $data, (int) $this->stores->getStore()->getId());

                    return $result->setData(['message' => 'Your comment has been submitted for review.']);
                }
                $event = $data['event'] ?? '';
                if (! is_string($event) || $event === 'enquiry') {
                    throw new \InvalidArgumentException('Choose a valid article event.');
                }$this->engagement->record($id, $event);

                return $result->setData(['message' => 'Thank you for your feedback.', 'stats' => $this->engagement->summary($id)]);
            }
            if ($intent === 'subscribe') {
                $this->subscriptions->request($data, (int) $this->stores->getStore()->getId());

                return $result->setData(['message' => 'If this address needs confirmation or management instructions, an email will arrive shortly.']);
            }
            $token = $data['token'] ?? '';
            if (! is_string($token) || strlen($token) > 150) {
                throw new \InvalidArgumentException('Use a valid subscription link.');
            }
            if ($intent === 'confirm') {
                $this->subscriptions->confirm($token);

                return $result->setData(['message' => 'Your article subscription is confirmed.']);
            }
            if (in_array($intent, ['manage', 'unsubscribe'], true)) {
                $this->subscriptions->manage($token, $data, $intent === 'unsubscribe');

                return $result->setData(['message' => $intent === 'unsubscribe' ? 'You have been unsubscribed.' : 'Your topic preferences have been saved.']);
            }
            throw new \InvalidArgumentException('Choose a valid reader action.');
        } catch (\InvalidArgumentException|NoSuchEntityException $exception) {
            return $result->setHttpResponseCode(422)->setData(['error' => $exception->getMessage()]);
        }
    }
}
