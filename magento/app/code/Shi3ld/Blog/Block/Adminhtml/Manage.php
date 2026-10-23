<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Shi3ld\Blog\Model\Repository;
use Shi3ld\Blog\Model\WidgetOptions;

class Manage extends Template
{
    public function __construct(Template\Context $context, private Repository $posts,
        private WidgetOptions $options,
        private AuthorizationInterface $authorization, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function categories(): array
    {
        return $this->posts->categories(false);
    }

    public function canPublish(): bool
    {
        return $this->authorization->isAllowed('Shi3ld_Content::visual_publish');
    }

    public function csrf(): string
    {
        return $this->getFormKey();
    }

    public function listing(): array
    {
        $search = $this->getRequest()->getParam('q', '');
        $status = $this->getRequest()->getParam('status', '');
        $category = (int) $this->getRequest()->getParam('category', 0);

        return $this->posts->listing(['search' => is_string($search) ? $search : '', 'status' => is_string($status) ? $status : '',
            'category_ids' => $category > 0 ? [$category] : []], max(1, (int) $this->getRequest()->getParam('p', 1)), 25, false);
    }

    public function fingerprint(int $id): string
    {
        return $this->posts->fingerprint($this->posts->post($id));
    }

    public function categoryFingerprint(array $row): string
    {
        return $this->posts->fingerprint($row);
    }

    public function selectedCategory(): ?array
    {
        $id = (int) $this->getRequest()->getParam('id');
        if (! $id) {
            return null;
        }
        try {
            return $this->posts->category($id);
        } catch (NoSuchEntityException $exception) {
            return null;
        }
    }

    public function builderConfig(): array
    {
        return ['fields' => $this->options->fields(), 'class' => WidgetOptions::WIDGET,
            'url' => $this->getUrl('shi3ld_blog/widget/preview'), 'csrf' => $this->csrf()];
    }

    public function search(): string
    {
        $value = $this->getRequest()->getParam('q', '');

        return is_string($value) ? mb_substr($value, 0, 100) : '';
    }

    public function articleOptions(): array
    {
        return $this->posts->listing([], 1, 100, false)['items'];
    }
}
