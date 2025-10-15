<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

class WebpParser extends AbstractImageParser
{
    public function getSupportedMimeTypes(): array
    {
        return ['image/webp'];
    }
}
