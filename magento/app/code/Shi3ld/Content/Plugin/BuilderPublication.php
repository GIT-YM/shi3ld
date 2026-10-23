<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Shi3ld\Content\Model\Builder\Runtime;
use Shi3ld\Content\Model\Editor\Documents;

class BuilderPublication
{
    public function __construct(private Runtime $runtime,
        private TypeListInterface $cache, private ResourceConnection $database) {}

    public function afterPublish(Documents $subject, array $result, string $type, int $id, int $version, string $author): array
    {
        $this->runtime->export();
        $this->database->getConnection()->update($this->database->getTableName('shi3ld_builder_workflow'), ['state' => 'published', 'note' => 'Published by '.$author], ['entity_type = ?' => $type, 'entity_id = ?' => $id, 'state IN (?)' => ['review', 'scheduled']]);
        foreach (['block_html', 'full_page'] as $name) {
            $this->cache->cleanType($name);
        }

        return $result;
    }
}
