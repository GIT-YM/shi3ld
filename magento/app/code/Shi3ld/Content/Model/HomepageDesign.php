<?php

declare(strict_types=1);

namespace Shi3ld\Content\Model;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\MediaGalleryApi\Api\Data\AssetInterfaceFactory;
use Magento\MediaGalleryApi\Api\GetAssetsByPathsInterface;
use Magento\MediaGalleryApi\Api\SaveAssetsInterface;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Model\Editor\Entities;
use Shi3ld\Content\Model\Editor\Markup;

class HomepageDesign
{
    private const THEME = 'app/design/frontend/Shi3ld/Alliance/';

    public function __construct(
        private PageFactory $pages,
        private BlockFactory $blocks,
        private Documents $documents,
        private Entities $entities,
        private Markup $markup,
        private Filesystem $filesystem,
        private ResourceConnection $database,
        private GetAssetsByPathsInterface $mediaAssets,
        private SaveAssetsInterface $saveMediaAssets,
        private AssetInterfaceFactory $assets
    ) {}

    public function install(bool $apply): array
    {
        $root = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $plan = [];
        foreach (['page' => ['shi3ld-home' => 'home'], 'block' => ['shi3ld_global_header' => 'header', 'shi3ld_global_footer' => 'footer']] as $type => $identifiers) {
            foreach ($identifiers as $identifier => $file) {
                $entity = ($type === 'page' ? $this->pages : $this->blocks)->create()->getCollection()
                    ->addFieldToFilter('identifier', $identifier)->getFirstItem();
                if (! $entity->getId()) {
                    throw new \RuntimeException('The existing CMS entity '.$identifier.' was not found. Complete the website installation first.');
                }
                $id = (int) $entity->getId();
                $state = $this->documents->get($type, $id);
                $live = $this->entities->snapshot($type, $id);
                $html = $root->readFile(self::THEME.'content/'.$file.'.html');
                if ($type === 'page') {
                    $html = $this->pageBuilderRows($html);
                }
                $payload = $live;
                $payload['html'] = $this->markup->validate($html, $html);
                if ($type === 'page') {
                    $payload['meta_title'] = 'Shi3ld Alliance | Spotless spaces. Safer homes.';
                    $payload['meta_description'] = 'Domestic, commercial and care-home cleaning, plus accessibility products. Explore Shi3ld Alliance services, shop grab rails and request a tailored quote.';
                    $payload['builder'] = [];
                }
                $normalised = $this->documents->validate($payload, $payload['html'], false, $type);
                $plan[] = ['type' => $type, 'id' => $id, 'identifier' => $identifier, 'live' => $live, 'state' => $state,
                    'payload' => $normalised, 'changed' => $normalised['html'] !== $live['html'] || ($type === 'page' && ($live['meta_title'] !== $normalised['meta_title'] || $live['meta_description'] !== $normalised['meta_description']))];
            }
        }
        $changes = array_filter($plan, fn (array $item): bool => $item['changed']);
        $result = ['applied' => false, 'backup' => null, 'entities' => array_map(fn (array $item): array => [
            'identifier' => $item['identifier'], 'id' => $item['id'], 'changed' => $item['changed'], 'has_draft' => (bool) $item['state']['has_draft'],
        ], $plan)];
        if (! $apply || ! $changes) {
            return $result;
        }
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory = 'shi3ld/cms-backups';
        $var->create($directory);
        $var->changePermissions($directory, 0700);
        $backup = $directory.'/homepage-design-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $var->writeFile($backup, json_encode(['created_at' => gmdate(DATE_ATOM), 'entities' => $plan], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $var->changePermissions($backup, 0600);
        $this->copyImages();
        $connection = $this->database->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($changes as $item) {
                $draft = $this->documents->save($item['type'], $item['id'], $item['payload'], $item['state']['version'], 'Homepage design installation', 'draft', $item['state']['live_hash']);
                $this->documents->publish($item['type'], $item['id'], $draft['version'], 'Homepage design installation');
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $result['applied'] = true;
        $result['backup'] = 'var/'.$backup;

        return $result;
    }

    private function pageBuilderRows(string $html): string
    {
        return preg_replace_callback('~<section\b.*?</section>~s', function (array $match): string {
            $section = str_replace(' data-content-type="row" data-appearance="full-width" data-element="main"', '', $match[0]);

            return '<div class="sa-home-row" data-content-type="row" data-appearance="full-width" data-element="main" data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}" data-background-type="image" data-video-loop="true" data-video-play-only-visible="true" data-video-lazy-load="true">'
                .'<div class="row-full-width-inner" data-element="inner"><div data-content-type="html" data-appearance="default" data-element="main">'.$section.'</div></div></div>';
        }, $html);
    }

    private function copyImages(): void
    {
        $root = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        foreach ($root->read(self::THEME.'web/images/alliance-home') as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'webp') {
                continue;
            }
            $name = basename($file);
            $target = 'wysiwyg/shi3ld/alliance-home/'.$name;
            if (! $media->isFile($target)) {
                $media->writeFile($target, $root->readFile($file));
            }
            if (! $this->mediaAssets->execute([$target])) {
                $bytes = $media->readFile($target);
                $dimensions = getimagesizefromstring($bytes);
                if (! $dimensions || $dimensions['mime'] !== 'image/webp') {
                    throw new \RuntimeException('A supplied homepage image is invalid.');
                }
                $this->saveMediaAssets->execute([$this->assets->create(['path' => $target, 'contentType' => 'image/webp',
                    'width' => $dimensions[0], 'height' => $dimensions[1], 'size' => strlen($bytes),
                    'title' => 'Shi3ld homepage · '.str_replace('-', ' ', pathinfo($name, PATHINFO_FILENAME))])]);
            }
        }
    }
}
