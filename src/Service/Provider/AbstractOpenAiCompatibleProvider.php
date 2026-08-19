<?php

namespace BoldMinded\DexterCore\Service\Provider;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use BoldMinded\DexterCore\Service\Options;

/**
 * Shared implementation for providers that speak the OpenAI Chat Completions API.
 * Subclasses only need to build the underlying client (e.g. with a custom base URI)
 * and supply a default model id.
 */
abstract class AbstractOpenAiCompatibleProvider implements ProviderInterface
{
    protected ClientContract $client;
    protected Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->client = $this->createClient($options);
    }

    abstract protected function createClient(Options $options): ClientContract;

    abstract protected function defaultModel(): string;

    public function request(string $prompt, string $content, string $requestType = ''): string
    {
        if ($requestType === 'image') {
            return $this->getImageDescription($prompt, $content);
        }

        $response = $this->client->chat()->create([
            'model' => $this->options->model ?: $this->defaultModel(),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant who is good at summarizing a document of text.'],
                ['role' => 'system', 'content' => 'Use the following to help provide further context to influence your response: ' . $content],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => (float) $this->options->temperature ?? 1,
            'frequency_penalty' => (float) $this->options->frequencyPenalty ?? 0,
            'presence_penalty' => (float) $this->options->presencePenalty ?? 0,
            'max_tokens' => $this->options->maxTokens ?? 300,
        ]);

        return $this->stripCodeFence($response->choices[0]->message->content ?? '');
    }

    /**
     * Callers expect a bare JSON literal, but models routinely wrap it in a
     * markdown fence regardless of the prompt telling them not to. Unwrap it
     * so json_decode() does not simply return null.
     *
     * Content that is not fenced is returned untouched.
     */
    protected function stripCodeFence(string $content): string
    {
        $trimmed = trim($content);

        if (!str_starts_with($trimmed, '```')) {
            return $content;
        }

        // The opening fence may carry a language hint (```json), so drop that
        // whole line, then the closing fence. Fences inside the payload are left
        // alone: only the outermost wrapper is removed.
        $unwrapped = preg_replace('/\A```[a-zA-Z0-9_+-]*[ \t]*\R?/', '', $trimmed, 1);

        if ($unwrapped === null) {
            return $content;
        }

        $unwrapped = preg_replace('/\R?[ \t]*```[ \t]*\z/', '', $unwrapped, 1);

        if ($unwrapped === null) {
            return $content;
        }

        return trim($unwrapped);
    }

    protected function getImageDescription(string $prompt, string $url): string
    {
        if (!$url) {
            return '';
        }

        $response = $this->client->chat()->create([
            'model' => $this->options->model ?: $this->defaultModel(),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant who can analyze images and provide detailed descriptions.'],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $url,
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => (float) $this->options->temperature ?? 1,
            'frequency_penalty' => (float) $this->options->frequencyPenalty ?? 0,
            'presence_penalty' => (float) $this->options->presencePenalty ?? 0,
            'max_tokens' => $this->options->maxTokens ?? 300,
        ]);

        return $this->stripCodeFence($response->choices[0]->message->content ?? '');
    }
}
