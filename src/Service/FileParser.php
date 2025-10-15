<?php

namespace BoldMinded\DexterCore\Service;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;
use BoldMinded\DexterCore\Service\DocumentParsers\FileParserFactory;

class FileParser
{
    private ConfigInterface $config;
    private LoggerInterface $logger;
    private FileParserFactory $fileParserFactory;
    private StopWordRemover $stopWordRemover;

    public function __construct(
        ConfigInterface $config,
        LoggerInterface $logger,
        FileParserFactory $fileParserFactory,
        StopWordRemover $stopWordRemover
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->fileParserFactory = $fileParserFactory;
        $this->stopWordRemover = $stopWordRemover;
    }

    public function parse(IndexableInterface $indexable): string
    {
        $whichOptions = $indexable->isImage() ? 'Image' : 'Document';
        $isParsingEnabled = $this->config->get(sprintf('parse%sContents.enabled', $whichOptions));

        if ($isParsingEnabled !== true) {
            return '';
        }

        $pathOptions = [
            $indexable->getAbsoluteUrl(),
            $indexable->getAbsolutePath(),
        ];

        $exceptions = [];
        $options = $this->config->get(sprintf('parse%sContents', $whichOptions)) ?: [];

        foreach ($pathOptions as $pathOption) {
            try {
                $text = $this->fileParserFactory->parseFile(
                    $pathOption,
                    $indexable->getMimeType(),
                    $options,
                );
                break;
            } catch (\Throwable $exception) {
                $exceptions[] = $exception;
            }
        }

        if (count($exceptions) === count($pathOptions)) {
            foreach ($exceptions as $exception) {
                $this->logger->debug($exception->getMessage());
            }
            return '';
        }

        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text ?? '');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim(strip_tags($text));

        // In the EE implementation, we'll fire a hook here.
        // For now, we'll just call the stop word remover directly.
        $text = $this->stopWordRemover->remove($text, $this->config->get('stopWords'));

        return $text;
    }
}
