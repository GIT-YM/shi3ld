<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Conversion;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Shi3ld\Blog\Model\Editorial\Engagement;
use Shi3ld\Blog\Model\Editorial\Tokens;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\Urls;

class Record implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(private Http $request, private JsonFactory $results,
        private Tokens $tokens, private Engagement $engagement,
        private Repository $posts, private Urls $urls) {}

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        try {
            if (strlen($this->request->getContent()) > 2000) {
                throw new \InvalidArgumentException('Invalid conversion.');
            }
            $data = json_decode($this->request->getContent(), true, 8, JSON_THROW_ON_ERROR);
            $id = (int) ($data['post_id'] ?? 0);
            $time = (int) ($data['timestamp'] ?? 0);
            $occurred = (int) ($data['occurred_at'] ?? 0);
            $reference = $data['reference'] ?? '';
            $attribution = $data['attribution'] ?? '';
            if (! $id || abs(time() - $time) > 300 || $occurred < time() - 15552000 || $occurred > time() + 300 || ! is_string($reference) || ! preg_match('/^[a-f0-9-]{36}$/iD', $reference) || ! is_string($attribution) || ! $this->tokens->verifyAt('attribution', $attribution, $id, $occurred)
                || ! hash_equals($this->tokens->sign('conversion', $id.'|'.$reference.'|'.$time.'|'.$occurred.'|'.hash('sha256', $attribution)), (string) $this->request->getHeader('X-Blog-Signature'))) {
                return $result->setHttpResponseCode(403)->setData(['error' => 'Invalid conversion signature.']);
            }
            $this->engagement->record($id, 'enquiry', $reference);
            $article = null;
            try {
                $post = $this->posts->post($id);
                $article = ['title' => $post['title'], 'url' => $this->urls->url('post', $post['slug'])];
            } catch (NoSuchEntityException) {
            }

            return $result->setData(['ok' => true, 'article' => $article]);
        } catch (\JsonException|\InvalidArgumentException) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid conversion payload.']);
        }
    }
}
