<?php

namespace Litzinger\DexterCore\Contracts;

interface IndexableInterface
{
    public function getScope(): string;

    public function getEntity();

    public function getTypes(): array;

    public function get(string $key): mixed;

    public function getValues(): array;

    public function getId(): int|string;

    public function getUniqueId(): string;

    public function getRelated(string $type): array;

    /**
     * @return CustomFieldInterface[]
     */
    public function getCustomFields(): array;
}
