<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

class JpegParser extends AbstractImageParser
{
    public function getSupportedMimeTypes(): array
    {
        return ['image/jpeg'];
    }
}
