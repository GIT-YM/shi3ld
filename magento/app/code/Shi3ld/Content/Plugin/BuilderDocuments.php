<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Shi3ld\Content\Model\Builder\Schema;
use Shi3ld\Content\Model\Builder\State;
use Shi3ld\Content\Model\Editor\Entities;

/** Entities-only: Documents has its own publish(), so its payload hook lives in BuilderPayload. */
class BuilderDocuments
{
    public function __construct(private State $state, private Schema $schema) {}

    public function afterSnapshot(Entities $subject, array $payload, string $type, int $id): array
    {
        if ($type !== 'asset' && ($settings = $this->state->get($type, $id)) !== null) {
            $payload['builder'] = $settings;
        }

        return $payload;
    }

    public function afterPublish(Entities $subject, mixed $result, string $type, int $id, array $payload): void
    {
        if ($type !== 'asset' && isset($payload['builder'])) {
            $this->state->save($type, $id, $this->schema->settings($payload['builder']));
        }
    }
}
