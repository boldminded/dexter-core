<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

use BoldMinded\DexterCore\Service\Options;
use BoldMinded\DexterCore\Service\Provider\AIProviderFactory;

abstract class AbstractDocumentParser implements FileParserInterface
{
    public function describe(
        string $filePath,
        array $options = [],
    ): string {
        $parseOptions = $this->getParseOptions($options);

        $provider = AIProviderFactory::create($parseOptions->ai);

        // For text documents, we first parse the file to extract text. We could send the file directly,
        // but parsing allows us to apply limits and other transformations before sending it to the AI provider
        $text = $this->parse($filePath, $options);

        $response = $provider->request($parseOptions->ai->prompt, $text, 'document');

        return $response;
    }

    protected function applyLimits(string $text, Options $options): string
    {
        // Apply word limit if specified
        if ($options->maxWords !== null) {
            $words = explode(' ', $text);
            if (count($words) > $options->maxWords) {
                $text = implode(' ', array_slice($words, 0, $options->maxWords));
            }
        }

        return $text;
    }

    protected function getParseOptions(array $options): Options
    {
        return DocumentOptions::fromArray($options);
    }
}
