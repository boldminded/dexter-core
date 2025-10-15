<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

class XmlParser extends AbstractDocumentParser
{
    public function parse(string $filePath, array $options = []): string
    {
        $parseOptions = $this->getParseOptions($options);

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Could not read XML file: %s', $filePath));
        }

        // Remove XML tags and get text content
        $text = strip_tags($content);
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return $this->applyLimits(trim($text), $parseOptions);
    }

    public function getSupportedMimeTypes(): array
    {
        return ['application/xml', 'text/xml'];
    }
}
