<?php

namespace BoldMinded\DexterCore\Service\Provider;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use BoldMinded\DexterCore\Service\Options;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

/**
 * OpenRouter (https://openrouter.ai) exposes an OpenAI-compatible Chat Completions
 * API, so we reuse the openai-php client pointed at OpenRouter's base URI.
 *
 * Models are referenced in OpenRouter form, e.g. "openai/gpt-4o" or
 * "anthropic/claude-3.5-sonnet". OpenRouter does not provide an embeddings API,
 * so embeddings are configured separately via the embeddingProvider setting.
 */
class OpenRouterProvider extends AbstractOpenAiCompatibleProvider
{
    protected function createClient(Options $options): ClientContract
    {
        return OpenAI::factory()
            ->withApiKey($options->key)
            ->withBaseUri('https://openrouter.ai/api/v1')
            // Recommended by OpenRouter for attribution and ranking on openrouter.ai.
            ->withHttpHeader('HTTP-Referer', 'https://boldminded.com/add-ons/dexter')
            ->withHttpHeader('X-Title', 'Dexter')
            // OpenRouter omits the OpenAI-specific token-detail keys that
            // openai-php requires, which would throw while deserializing an
            // otherwise successful (and already billed) response.
            ->withHttpClient(new OpenRouterResponseSanitizer(
                Psr18ClientDiscovery::find(),
                Psr17FactoryDiscovery::findStreamFactory(),
            ))
            ->make();
    }

    protected function defaultModel(): string
    {
        return 'openai/gpt-4o';
    }
}
