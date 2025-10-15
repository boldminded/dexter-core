<?php

namespace BoldMinded\DexterCore\Service\DocumentParsers;

use BoldMinded\DexterCore\Contracts\ConfigInterface;

class FileParserFactory
{
    private array $parsers = [];
    private array $mimeTypeMap = [];

    public function __construct(public ConfigInterface $config)
    {
        $this->registerDefaultParsers();
    }

    private function registerDefaultParsers(): void
    {
        $this->registerParser(new PdfParser());
        $this->registerParser(new TxtParser());
        $this->registerParser(new CsvParser());
        $this->registerParser(new XmlParser());
        $this->registerParser(new DocParser());
        $this->registerParser(new JpegParser());
        $this->registerParser(new PngParser());
        $this->registerParser(new GifParser());
        $this->registerParser(new WebpParser());
    }

    public function registerParser(FileParserInterface $parser): void
    {
        $className = get_class($parser);
        $this->parsers[$className] = $parser;

        foreach ($parser->getSupportedMimeTypes() as $mimeType) {
            $this->mimeTypeMap[$mimeType] = $className;
        }
    }

    public function createParser(string $mimeType, string $filePath = ''): FileParserInterface
    {
        if (!isset($this->mimeTypeMap[$mimeType])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported MIME type: %s for %s',
                $mimeType,
                $filePath ? 'file at ' . $filePath : 'unknown file'
            ));
        }

        $className = $this->mimeTypeMap[$mimeType];
        return $this->parsers[$className];
    }

    public function parseFile(
        string $filePath,
        ?string $mimeType = null,
        array $options = [],
    ): string {
        if ($mimeType === null) {
            $mimeType = $this->detectMimeType($filePath);
        }

        $parser = $this->createParser($mimeType, $filePath);
        return $parser->parse($filePath, $this->buildOptions($mimeType, $options));
    }

    public function describeFile(
        string $filePath,
        ?string $mimeType = null,
        array $options = [],
    ): string {
        if ($mimeType === null) {
            $mimeType = $this->detectMimeType($filePath);
        }

        $parser = $this->createParser($mimeType, $filePath);
        return $parser->describe($filePath, $this->buildOptions($mimeType, $options));
    }

    private function buildOptions(string $mimeType, array $options): array
    {
        $provider = $this->config->get('aiProvider', 'openAi');

        $whichOptions = in_array($mimeType, $this->imageMimeTypes()) ? 'Image' : 'Document';
        $prompt = $this->config->get(sprintf('parse%sContents.describePrompt', $whichOptions)) ?: 'Describe this file';

        $options['ai'] = [
            'provider' => $provider,
            'key' => $this->config->get($provider . '.key'),
            'model' => $this->config->get($provider . '.model'),
            'embedModel' => $this->config->get($provider . '.embedModel'),
            'temperature' => $this->config->get($provider . '.temperature'),
            'frequencyPenalty' => $this->config->get($provider . '.frequencyPenalty'),
            'presencePenalty' => $this->config->get($provider . '.presencePenalty'),
            'maxTokens' => $this->config->get($provider . '.maxTokens'),
            'prompt' => $prompt,
        ];

        return $options;
    }

    public function getSupportedMimeTypes(): array
    {
        return array_keys($this->mimeTypeMap);
    }

    private function detectMimeType(string $filePath): string
    {
        $detector = new MimeDetector();
        $mimeType = $detector->detect($filePath);

        return $mimeType;
    }

    private function imageMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
    }
}
