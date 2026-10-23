<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

class PrivateCmsPreview
{
    public function __construct(private \Magento\Framework\App\Request\Http $request, private \Shi3ld\Content\Model\Editor\Preview $preview) {}

    public function afterIsEnabled(\Magento\PageCache\Model\Config $config, bool $enabled): bool
    {
        return $this->request->getParam('shi3ld_preview') ? false : $enabled;
    }

    public function beforeSendResponse(\Magento\Framework\App\Response\Http $response): void
    {
        if ($this->request->getParam('shi3ld_preview')) {
            $response->setNoCacheHeaders();
            $response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
            $response->setHeader('Referrer-Policy', 'no-referrer', true);
            if (!$this->preview->get()) {
                $response->setHttpResponseCode(403)->setBody('<!doctype html><html lang="en"><meta charset="utf-8"><title>Draft preview expired</title><h1>This draft preview has expired.</h1><p>Return to the visual editor and open a new preview.</p></html>');
            } else {
                $response->setBody(preg_replace('~(<body\b[^>]*>)~i', '$1<div class="notice" role="status">Private draft preview — this content has not been published.</div>', $response->getBody(), 1));
            }
        }
    }
}
