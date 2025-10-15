<?php

namespace Litzinger\DexterCore\Service\Provider;

use OpenAI;
use Litzinger\DexterCore\Service\Options;

class OpenAIProvider implements ProviderInterface
{
    private $client;
    private Options $options;

    public function __construct(Options $options)
    {
        $this->client = OpenAI::client($options->key);
        $this->options = $options;
    }

    public function request(string $prompt, string $content, string $requestType = ''): string
    {
        if ($requestType === 'image') {
            return $this->getImageDescription($prompt, $content);
        }

        $response = $this->client->chat()->create([
            'model' => $this->options->model ?? 'gpt-4o',
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

        return $response->choices[0]->message->content ?? '';
    }

    private function getImageDescription(string $prompt, string $url): string
    {
        if (!$url) {
            return '';
        }

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o',
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

        return $response->choices[0]->message->content ?? '';
    }
}
