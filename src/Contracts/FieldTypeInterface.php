<?php

namespace Litzinger\DexterCore\Contracts;

interface FieldTypeInterface
{
    public function process(
        IndexableInterface $indexable,
        ConfigInterface $config,
        int $fieldId,
        array $fieldSettings,
        mixed $fieldValue,
        $fieldFacade = null
    ): mixed;

    public function setsMultipleProperties(): bool;
}
