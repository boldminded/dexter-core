<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

class PngParser extends AbstractImageParser
{
    public function getSupportedMimeTypes(): array
    {
        return ['image/png', 'image/x-png'];
    }
}
