<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Widget;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\WidgetOptions;
use Shi3ld\Content\Model\Editor\Renderer;

class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(Context $context, private JsonFactory $results,
        private WidgetOptions $options, private Renderer $renderer,
        private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        try {
            $settings = json_decode((string) $this->getRequest()->getParam('settings'), true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($settings)) {
                throw new \InvalidArgumentException('Invalid widget settings.');
            }
            $directive = $this->options->directive($settings);

            return $result->setData(['directive' => $directive, 'html' => $this->renderer->render($directive)]);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to preview blog widget.', ['exception' => $exception]);

            return $result->setHttpResponseCode(400)->setData(['error' => 'The widget could not be previewed. Check your settings and sign in again if your session expired.']);
        }
    }
}
