<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

class JpegParser extends AbstractImageParser
{
    public function getSupportedMimeTypes(): array
    {
        return ['image/jpeg'];
    }
}
