<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

use Litzinger\DexterCore\Service\Options;
use Litzinger\DexterCore\Service\Provider\AIOptions;

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
