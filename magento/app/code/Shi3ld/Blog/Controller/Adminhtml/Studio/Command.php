<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Controller\Adminhtml\Studio;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Shi3ld\Blog\Model\Editorial\Studio;

class Command extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(Context $context, private Studio $studio,
        private JsonFactory $results, private Validator $csrf,
        private LoggerInterface $logger)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        try {
            if (! $this->csrf->validate($this->getRequest())) {
                return $result->setHttpResponseCode(403)->setData(['error' => 'Your session changed. Sign in and reload this page.']);
            }
            $json = $this->getRequest()->getParam('data', '{}');
            if (! is_string($json) || strlen($json) > 700000) {
                throw new \InvalidArgumentException('The blog request is too large.');
            }$data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new \InvalidArgumentException('Choose a valid blog action.');
            }
            $intent = $this->getRequest()->getParam('intent', '');
            if (! is_string($intent)) {
                throw new \InvalidArgumentException('Choose a valid blog action.');
            }
            $file = $this->getRequest()->getFiles($intent === 'upload' ? 'image' : 'package');

            return $result->setData($this->studio->run($intent, $data, $this->_auth->getUser(), $this->_authorization->isAllowed('Shi3ld_Content::visual_publish'), $this->_authorization->isAllowed('Shi3ld_Content::design'), is_array($file) ? $file : null));
        } catch (LocalizedException|\InvalidArgumentException|\JsonException $exception) {
            return $result->setHttpResponseCode(409)->setData(['error' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->logger->error('Blog management action failed.', ['exception' => $exception]);

            return $result->setHttpResponseCode(500)->setData(['error' => 'The blog action could not be completed. Refresh the page and try again.']);
        }
    }
}
