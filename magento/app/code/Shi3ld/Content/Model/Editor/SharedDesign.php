<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model\Editor;

use Magento\Cms\Model\BlockFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Shi3ld\Content\Model\SiteAccess;

class SharedDesign
{
    public function __construct(private BlockFactory $blocks, private Filesystem $filesystem, private Markup $markup) {}

    public function export(): void
    {
        $data = ['updated_at' => gmdate(DATE_ATOM)];
        foreach (['header','footer'] as $region) {
            $block = $this->blocks->create()->getCollection()->addFieldToFilter('identifier', 'shi3ld_global_'.$region)->getFirstItem();
            if (!$block->getId() || !$block->getIsActive()) {
                $data[$region] = '';
                continue;
            }
            $data[$region] = $this->markup->validate((string) $block->getContent(), (string) $block->getContent()) ?: '<!-- Shared website region intentionally empty. -->';
        }
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create(SiteAccess::DIRECTORY);
        $path = SiteAccess::DIRECTORY.'/design.json';
        $temporary = $path.'.'.bin2hex(random_bytes(8));
        $directory->writeFile($temporary, json_encode($data, JSON_THROW_ON_ERROR));
        $directory->changePermissions($temporary, 0644);
        $directory->renameFile($temporary, $path);
    }
}
