<?php

namespace Litzinger\DexterCore\Contracts;

interface TranslatorInterface
{
    public function get(string $key): string;
}
