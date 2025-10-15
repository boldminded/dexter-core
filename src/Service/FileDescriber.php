<?php

namespace Litzinger\DexterCore\Service;

use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\LoggerInterface;
use Litzinger\DexterCore\Contracts\IndexableInterface;
use Litzinger\DexterCore\Service\DocumentParsers\FileParserFactory;

class FileDescriber
{
    private ConfigInterface $config;
    private LoggerInterface $logger;
    private FileParserFactory $fileParserFactory;

    public function __construct(
        ConfigInterface $config,
        LoggerInterface $logger,
        FileParserFactory $fileParserFactory
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->fileParserFactory = $fileParserFactory;
    }

    public function describe(IndexableInterface $indexable): string
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
                $text = $this->fileParserFactory->describeFile(
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

        return $text ?? '';
    }

    public function isJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
