<?php

namespace BoldMinded\DexterCore\Service\Provider;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use BoldMinded\DexterCore\Service\Options;

class OpenAIProvider extends AbstractOpenAiCompatibleProvider
{
    protected function createClient(Options $options): ClientContract
    {
        return OpenAI::client($options->key);
    }

    protected function defaultModel(): string
    {
        return 'gpt-4o';
    }
}
