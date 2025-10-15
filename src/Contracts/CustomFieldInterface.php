<?php

namespace Litzinger\DexterCore\Contracts;

interface CustomFieldInterface
{
    public function getName(): string;

    public function getId(): int|string;

    public function getType(): string;

    public function getSettings(): array;

    public function getValue(): mixed;

    public function getFacade(): object;
}
