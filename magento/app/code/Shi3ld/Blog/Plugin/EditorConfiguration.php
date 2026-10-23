<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Plugin;

use Magento\Backend\Model\UrlInterface;
use Shi3ld\Blog\Model\Editorial\Metadata;
use Shi3ld\Blog\Model\Editorial\Starters;
use Shi3ld\Blog\Model\WidgetOptions;
use Shi3ld\Content\Model\Editor\Entities;

class EditorConfiguration
{
    public function __construct(private WidgetOptions $options, private UrlInterface $backend,
        private Metadata $metadata, private Starters $starters) {}

    public function afterConfiguration(Entities $subject, array $config, string $type, int $id): array
    {
        $config['blogWidget'] = ['fields' => $this->options->fields(), 'class' => WidgetOptions::WIDGET];
        if (in_array($type, ['article', 'blog_locale'], true)) {
            $config['blogEditorial'] = ['url' => $this->backend->getUrl('shi3ld_blog/studio/command'), 'managementUrl' => $this->backend->getUrl('shi3ld_blog/studio/index'), 'type' => $type, 'id' => $id, 'defaults' => $this->metadata->defaults(), 'starters' => $this->starters->all()];
        }

        return $config;
    }
}
