<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Access;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Shi3ld\Content\Model\SiteAccess;

class Offline implements HttpGetActionInterface
{
    public function __construct(
        private RawFactory $results,
        private SiteAccess $siteAccess,
        private Http $request,
        private FrontNameResolver $adminFrontName,
        private LayoutInterface $layout,
        private CspNonceProvider $nonce
    ) {
    }

    public function execute(): Raw
    {
        $result = $this->results->create()
            ->setHttpResponseCode(503)
            ->setHeader('Cache-Control', 'private, no-store, max-age=0', true)
            ->setHeader('Retry-After', '3600', true);

        $originalPath = (string) $this->request->getServer('SHI3LD_ORIGINAL_URI', '');
        $json = str_contains((string) $this->request->getHeader('Accept'), 'application/json')
            || $this->request->isXmlHttpRequest()
            || preg_match('#^/(?:rest|graphql)(?:/|\?|$)#', $originalPath);

        if ($json) {
            return $result->setHeader('Content-Type', 'application/json; charset=UTF-8', true)
                ->setContents(json_encode([
                    'message' => $this->siteAccess->getMessage(),
                    'status' => 'offline',
                ], JSON_THROW_ON_ERROR));
        }

        $automatic = $this->siteAccess->redirectsAdministrators()
            && in_array($this->request->getServer('SHI3LD_ORIGINAL_METHOD', 'GET'), ['GET', 'HEAD'], true);
        $block = $this->layout->createBlock(Template::class)
            ->setTemplate('Shi3ld_Content::offline.phtml')
            ->setData('offline_message', $this->siteAccess->getMessage())
            ->setData('admin_url', '/' . $this->adminFrontName->getFrontName() . '/shi3ld_content/siteaccess/index/')
            ->setData('automatic_preview', $automatic)
            ->setData('script_nonce', $automatic ? $this->nonce->generateNonce() : '')
            ->setData('admin_preview_url', '/' . $this->adminFrontName->getFrontName() . '/shi3ld_content/siteaccess/frontend/');

        return $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
            ->setContents($block->toHtml());
    }
}
