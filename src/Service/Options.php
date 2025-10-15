<?php

namespace BoldMinded\DexterCore\Service;

interface Options
{
    public static function fromArray(array $options): self;
}
