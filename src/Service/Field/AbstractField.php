<?php

namespace BoldMinded\DexterCore\Service\Field;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;
use BoldMinded\DexterCore\Contracts\FieldTypeInterface;

class AbstractField implements FieldTypeInterface
{
    public function process(
        IndexableInterface $indexable,
        ConfigInterface $config,
        int|string $fieldId,
        array $fieldSettings,
        mixed $fieldValue,
        $fieldFacade = null
    ): mixed {
        return $fieldValue;
    }

    public function setsMultipleProperties(): bool
    {
        return false;
    }
}
