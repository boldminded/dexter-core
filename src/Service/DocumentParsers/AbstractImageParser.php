<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

use BoldMinded\DexterCore\Service\Options;
use BoldMinded\DexterCore\Service\Provider\AIProviderFactory;

abstract class AbstractImageParser implements FileParserInterface
{
    public function parse(
        string $filePath,
        array $options = [],
    ): string {
        return '';
    }

    public function describe(
        string $filePath,
        array $options = [],
    ): string {
        $parseOptions = $this->getParseOptions($options);

        $provider = AIProviderFactory::create($parseOptions->ai);

        $prompt = $parseOptions->ai->prompt . '. Return a JSON object literal with a "description" key containing the
            description of the image, and a "tags" key containing up to 3 to 5 important keywords to categorize the image.
            Make the keywords in title case, preferably a single word, and do not include any punctuation or special characters.
            Do not wrap the JSON object in a code block.';

        return $provider->request($prompt, $filePath, 'image');
    }

    protected function getParseOptions(array $options): Options
    {
        return ImageOptions::fromArray($options);
    }
}
