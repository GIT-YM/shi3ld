<?php

declare(strict_types=1);

namespace Shi3ld\Content\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Shi3ld\Content\Model\Builder\Runtime;
use Shi3ld\Content\Model\Editor\Preview;
use Shi3ld\Content\Model\Editor\Renderer;

class BuilderPreview extends Template
{
    public function __construct(Context $context, private Preview $preview,
        private Renderer $renderer, private Runtime $builder, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function content(): string
    {
        $draft = $this->preview->get();
        if (! $draft || $draft['type'] !== 'asset') {
            return '';
        }
        $payload = $draft['payload'];
        $result = $this->builder->render($this->renderer->render($payload['html']), $payload['builder'] ?? [], ['key' => 'asset-'.$draft['id'], 'asset_key' => $draft['identifier'], 'fields' => ['title' => $payload['title'], 'excerpt' => 'Example introduction', 'image' => '/services/assets/blog-placeholder.svg', 'url' => '/blog']], true);

        return ($result['css'] ? '<style>'.$result['css'].'</style>' : '').$result['html'];
    }
}
