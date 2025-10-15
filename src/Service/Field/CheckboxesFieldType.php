<?php

namespace BoldMinded\DexterCore\Service\Field;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;

class CheckboxesFieldType extends AbstractField
{
    public function process(
        IndexableInterface $indexable,
        ConfigInterface $config,
        int $fieldId,
        array $fieldSettings,
        $fieldValue,
        $fieldFacade = null
    ): mixed {
        return explode('|', $fieldValue);
    }
}
