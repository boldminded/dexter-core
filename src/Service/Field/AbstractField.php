<?php

namespace Litzinger\DexterCore\Service\Field;

use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\IndexableInterface;
use Litzinger\DexterCore\Contracts\FieldTypeInterface;

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
