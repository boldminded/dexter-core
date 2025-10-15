<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

class GifParser extends AbstractImageParser
{
    public function getSupportedMimeTypes(): array
    {
        return ['image/gif', 'image/x-gif'];
    }
}
