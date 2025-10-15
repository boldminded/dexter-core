<?php

namespace BoldMinded\DexterCore\Service;

class FieldValue
{
    public static function isJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
