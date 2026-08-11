<?php

namespace BoldMinded\DexterCore\Service\Field;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;
use BoldMinded\DexterCore\Contracts\FieldTypeInterface;

class AbstractTextField implements FieldTypeInterface
{
    public function process(
        IndexableInterface $indexable,
        ConfigInterface $config,
        int|string $fieldId,
        array $fieldSettings,
        $fieldValue,
        $fieldFacade = null
    ): mixed {
        return strip_tags($fieldValue ?? '');
    }

    public function setsMultipleProperties(): bool
    {
        return false;
    }
}
