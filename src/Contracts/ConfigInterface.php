<?php

namespace BoldMinded\DexterCore\Contracts;

interface ConfigInterface
{
    public function get(string $key, string|null $index = null, array|null $values = null): mixed;

    public function getAll(): array;

    public function setAll(array $options): void;
}
