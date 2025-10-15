<?php

namespace Litzinger\DexterCore\Service\Field;

use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\IndexableInterface;
use Litzinger\DexterCore\Contracts\FieldTypeInterface;

class AbstractTextField implements FieldTypeInterface
{
    public function process(
        IndexableInterface $indexable,
        ConfigInterface $config,
        int $fieldId,
        array $fieldSettings,
        $fieldValue,
        $fieldFacade = null
    ): mixed {
        return strip_tags($value ?? '');
    }

    public function setsMultipleProperties(): bool
    {
        return false;
    }
}
