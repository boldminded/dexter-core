<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

use BoldMinded\DexterCore\Service\Options;
use BoldMinded\DexterCore\Service\Provider\AIOptions;

class ImageOptions implements Options
{
    public function __construct(
        public Options|null $ai,
    ) {
    }
    public static function fromArray(array $options): self
    {
        $aiOptions = AIOptions::fromArray($options['ai'] ?? []);

        return new self(
            ai: $aiOptions
        );
    }
}
