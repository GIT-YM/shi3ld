<?php

declare(strict_types=1);

namespace Shi3ld\Content\Api;

/** Extension point for content owned by another Magento module. */
interface EditorEntityInterface
{
    public function load(int $id): \Magento\Framework\DataObject;
    public function snapshot(int $id): array;
    public function publish(int $id, array $payload): void;
    public function url(int $id): string;
    public function navigation(): array;
    public function validate(array $payload): array;
    public function lock(int $id): void;
    public function configuration(int $id): array;
}
