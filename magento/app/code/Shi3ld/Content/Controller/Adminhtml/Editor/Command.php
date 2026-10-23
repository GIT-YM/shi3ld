<?php

declare(strict_types=1);

namespace Shi3ld\Content\Controller\Adminhtml\Editor;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Security\Model\AdminSessionsManager;
use Psr\Log\LoggerInterface;
use Shi3ld\Content\Model\Builder\Commands;
use Shi3ld\Content\Model\Builder\Runtime;
use Shi3ld\Content\Model\Editor\Documents;
use Shi3ld\Content\Model\Editor\Entities;
use Shi3ld\Content\Model\Editor\Images;
use Shi3ld\Content\Model\Editor\Preview;
use Shi3ld\Content\Model\Editor\Renderer;
use Shi3ld\Content\Model\Editor\SharedDesign;

class Command extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Shi3ld_Content::visual_edit';

    public function __construct(
        Context $context, private Documents $documents, private Entities $entities, private Renderer $renderer,
        private Preview $preview, private Images $images, private SharedDesign $design, private JsonFactory $results,
        private AdminSessionsManager $sessions, private TypeListInterface $cache, private LoggerInterface $logger,
        private Commands $advanced, private Runtime $builder
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->results->create()->setHeader('Cache-Control', 'private, no-store', true);
        try {
            $type = (string) $this->getRequest()->getParam('type', 'page');
            $id = (int) $this->getRequest()->getParam('id');
            $entity = $this->entities->load($type, $id);
            $intent = (string) $this->getRequest()->getParam('intent');
            $version = (int) $this->getRequest()->getParam('version', -1);
            $author = (string) $this->_auth->getUser()->getUserName();
            $current = $this->documents->get($type, $id);
            $designer = $this->_authorization->isAllowed('Shi3ld_Content::design');
            if (in_array($intent, Commands::INTENTS, true)) {
                return $result->setData($this->advanced->run($intent, $type, $id, $current, $this->getRequest(), $this->_auth->getUser(),
                    $this->_authorization->isAllowed('Shi3ld_Content::visual_publish'), $designer));
            }
            $builderContext = ['key' => $type.'-'.$id, 'asset_key' => $type === 'asset' ? $entity->getIdentifier() : null, 'path' => (string) parse_url($this->entities->url($type, $id), PHP_URL_PATH),
                'editing' => true, 'fields' => ['title' => $current['payload']['title'], 'excerpt' => $current['payload']['excerpt'] ?? '',
                    'author' => $current['payload']['author'] ?? '', 'image' => ! empty($current['payload']['featured_image']) ? '/media/'.$current['payload']['featured_image'] : '/services/assets/blog-placeholder.svg']];
            if ($intent === 'published') {
                $published = $this->entities->snapshot($type, $id);
                $builderContext['fields'] = array_replace($builderContext['fields'], ['title' => $published['title'], 'excerpt' => $published['excerpt'] ?? '', 'author' => $published['author'] ?? '']);
                $rendered = $this->builder->render($this->renderer->render($published['html'], $type === 'blog_locale' ? (int) $entity->getData('store_id') : null), $published['builder'] ?? [], $builderContext, true);

                return $result->setData(['payload' => $published] + $rendered);
            }
            if ($intent === 'media') {
                return $result->setData(['media' => $this->images->listing()]);
            }
            if ($intent === 'upload') {
                return $result->setData(['image' => $this->images->upload((array) $this->getRequest()->getFiles('image'))]);
            }
            if ($intent === 'revision') {
                return $result->setData(['payload' => $this->documents->revision($type, $id, (int) $this->getRequest()->getParam('revision_id'))]);
            }
            if ($intent === 'render' || $intent === 'save') {
                $payload = json_decode((string) $this->getRequest()->getParam('payload'), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new LocalizedException(__('The page content could not be read.'));
                }
                $this->advanced->assert($payload, $current['payload'], $designer);
                if ($intent === 'render') {
                    $baseline = $this->entities->snapshot($type, $id)['html'].$current['payload']['html'];
                    if ($this->getRequest()->getParam('revision_id')) {
                        $baseline .= $this->documents->revision($type, $id, (int) $this->getRequest()->getParam('revision_id'))['html'];
                    }
                    $payload = $this->documents->validate($payload, $baseline, true, $type);
                    $builderContext['fields'] = array_replace($builderContext['fields'], ['title' => $payload['title'], 'excerpt' => $payload['excerpt'] ?? '', 'author' => $payload['author'] ?? '']);

                    return $result->setData($this->builder->render($this->renderer->render($payload['html'], $type === 'blog_locale' ? (int) $entity->getData('store_id') : null), $payload['builder'] ?? [], $builderContext, true));
                }
                $state = $this->documents->save($type, $id, $payload, $version, $author, 'draft', (string) $this->getRequest()->getParam('live_hash'));
            } elseif ($intent === 'restore') {
                $payload = $this->documents->revision($type, $id, (int) $this->getRequest()->getParam('revision_id'));
                $this->advanced->assert($payload, $current['payload'], $designer);
                $state = $this->documents->save($type, $id, $payload, $version, $author, 'restore', $current['live_hash']);
            } elseif ($intent === 'publish') {
                if (! $this->_authorization->isAllowed('Shi3ld_Content::visual_publish')) {
                    return $result->setHttpResponseCode(403)->setData(['error' => 'Your role can save drafts but cannot publish.']);
                }
                $state = $this->documents->publish($type, $id, $version, $author);
                foreach (['block_html', 'full_page'] as $cacheType) {
                    $this->cache->cleanType($cacheType);
                }
                if ($type === 'block') {
                    $this->design->export();
                }
            } elseif ($intent === 'preview') {
                $previewId = $this->preview->start(['type' => $type, 'id' => $id, 'identifier' => $entity->getIdentifier(), 'payload' => $current['payload']],
                    $this->_auth->getUser(), (int) $this->sessions->getCurrentSession()->getId());

                return $result->setData(['url' => $this->entities->url($type, $id).(str_contains($this->entities->url($type, $id), '?') ? '&' : '?').'shi3ld_preview='.$previewId]);
            } else {
                throw new LocalizedException(__('Choose a valid editor action.'));
            }

            return $result->setData(['state' => $state]);
        } catch (LocalizedException|\InvalidArgumentException|\JsonException $exception) {
            return $result->setHttpResponseCode(409)->setData(['error' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->logger->error('Shi3ld visual editor action failed.', ['exception' => $exception]);

            return $result->setHttpResponseCode(500)->setData(['error' => 'This change could not be saved. Please reload the editor and try again.']);
        }
    }
}
