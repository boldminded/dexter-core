<?php

namespace Litzinger\DexterCore\Contracts;

interface FieldTypeHandlerFactoryInterface
{
    public static function create(string $fieldType): ?FieldTypeInterface;
}
