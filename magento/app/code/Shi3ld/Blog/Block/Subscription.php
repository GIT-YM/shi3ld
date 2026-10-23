<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Shi3ld\Blog\Model\Editorial\Subscriptions;

class Subscription extends Template
{
    public function __construct(Context $context, private Subscriptions $subscriptions, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function state(): array
    {
        $mode = $this->getRequest()->getParam('mode', '');
        $token = $this->getRequest()->getParam('token', '');
        try {
            if (! in_array($mode, ['confirm', 'manage'], true) || ! is_string($token) || strlen($token) > 150) {
                throw new \InvalidArgumentException('This subscription link is invalid or expired.');
            }
            $row = $this->subscriptions->inspect($mode, $token);

            return ['mode' => $mode, 'token' => $token, 'category_ids' => json_decode($row['category_ids'] ?: '[]', true, 8, JSON_THROW_ON_ERROR), 'status' => $row['status']];
        } catch (\InvalidArgumentException) {
            return ['error' => 'This subscription link is invalid or expired. Request a new link from the journal.'];
        }
    }
}
