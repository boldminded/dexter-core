<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

class TxtParser extends AbstractDocumentParser
{
    public function parse(string $filePath, array $options = []): string
    {
        $parseOptions = $this->getParseOptions($options);
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Could not read file: %s', $filePath));
        }

        return $this->applyLimits($content, $parseOptions);
    }

    public function getSupportedMimeTypes(): array
    {
        return ['text/plain'];
    }
}
