<?php

namespace BoldMinded\DexterCore\Service\Provider;

use BoldMinded\DexterCore\Service\Options;

class AIOptions implements Options
{
    public function __construct(
        public ?string $provider = null,
        public ?string $prompt = null,
        public ?string $key = null,
        public ?string $secret = null,
        public ?string $model = null,
        public ?string $embedModel = null,
        public ?float $temperature = null,
        public ?float $frequencyPenalty = null,
        public ?float $presencePenalty = null,
        public ?int $maxTokens = null,
    ) {
    }

    public static function fromArray(array $options): self
    {
        return new self(
            $options['provider'] ?? null,
            $options['prompt'] ?? null,
            $options['key'] ?? null,
            $options['secret'] ?? null,
            $options['model'] ?? 'gpt-4o',
            $options['embedModel'] ?? 'text-embedding-3-small',
            $options['temperature'] ?? 0.7,
            $options['frequencyPenalty'] ?? 0.0,
            $options['presencePenalty'] ?? 0.0,
            $options['maxTokens'] ?? 300,
        );
    }
}
