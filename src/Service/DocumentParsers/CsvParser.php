<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

class CsvParser extends AbstractDocumentParser
{
    public function parse(string $filePath, array $options = []): string
    {
        $parseOptions = $this->getParseOptions($options);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not read CSV file: %s', $filePath));
        }

        $text = '';
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($parseOptions->maxPages !== null && $rowCount >= $parseOptions->maxPages) {
                break;
            }

            $text .= implode(' ', $row) . "\n";
            $rowCount++;

            // Check word limit
            if ($parseOptions->maxWords !== null) {
                $wordCount = str_word_count($text);
                if ($wordCount >= $parseOptions->maxWords) {
                    break;
                }
            }
        }

        fclose($handle);

        return $this->applyLimits(trim($text), $parseOptions);
    }

    public function getSupportedMimeTypes(): array
    {
        return ['text/csv', 'application/csv'];
    }
}
