<?php

declare(strict_types=1);

namespace Shi3ld\Content\Plugin;

use Shi3ld\Content\Model\Builder\Schema;
use Shi3ld\Content\Model\Editor\Documents;

/** Documents-only: Magento binds every after* method of a plugin to each type it is registered on. */
class BuilderPayload
{
    public function __construct(private Schema $schema) {}

    public function afterValidate(Documents $subject, array $result, array $payload, string $baseline, bool $render = false, string $type = 'page'): array
    {
        if (array_key_exists('builder', $payload) || str_contains($payload['html'], 'data-builder-')) {
            $result['builder'] = $this->schema->settings((array) ($payload['builder'] ?? []));
            $html = html_entity_decode($payload['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $result['builder']['has_audience_rules'] = (bool) preg_match('/"audience"\s*:\s*"(?:guest|customer|staff)"/', $html);
            $result['builder']['has_date_rules'] = (bool) preg_match('/"(?:start|end)"\s*:\s*"2\d{3}-/', $html);
            $this->schema->templateContent($result['builder'], $payload['html']);
        }

        return $result;
    }
}
