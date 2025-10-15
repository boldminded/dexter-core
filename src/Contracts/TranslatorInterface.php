<?php

namespace BoldMinded\DexterCore\Contracts;

interface TranslatorInterface
{
    public function get(string $key): string;
}
