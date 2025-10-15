<?php

namespace Litzinger\DexterCore\Service\DocumentParsers;

use Smalot\PdfParser\Parser;

class PdfParser extends AbstractDocumentParser
{
    private $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function parse(string $filePath, array $options = []): string
    {
        $parseOptions = $this->getParseOptions($options);

        $pdf = $this->parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $text = '';
        $pageCount = 0;

        foreach ($pages as $page) {
            if ($parseOptions->maxPages !== null && $pageCount >= $parseOptions->maxPages) {
                break;
            }

            $pageText = $page->getText();
            $text .= $pageText . "\n";
            $pageCount++;

            // Check word limit during processing to avoid parsing unnecessary pages
            if ($parseOptions->maxWords !== null) {
                $wordCount = str_word_count($text);
                if ($wordCount >= $parseOptions->maxWords) {
                    break;
                }
            }
        }

        return $this->applyLimits(trim($text), $parseOptions);
    }

    public function getSupportedMimeTypes(): array
    {
        return ['application/pdf'];
    }
}
