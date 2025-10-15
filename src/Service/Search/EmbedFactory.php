<?php

namespace BoldMinded\DexterCore\Service\Search;

use BoldMinded\DexterCore\Contracts\ConfigInterface;

class EmbedFactory
{
    public function __construct(public ConfigInterface $config)
    {
    }

    public function create(string $text): array
    {
        $provider = $this->config->get('aiProvider');

        if ($provider === 'openAi') {
            return $this->createOpenAIEmbed(
                $text,
                $this->config->get($provider . '.key')
            );
        }

        return [];
    }

    private function createOpenAIEmbed(string $text, string $apiKey): array
    {
        $ch = curl_init('https://api.openai.com/v1/embeddings');

        $payload = json_encode([
            'input' => $text,
            'model' => 'text-embedding-3-small', // Or 'text-embedding-ada-002'
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($status !== 200 || !isset($result['data'][0]['embedding'])) {
            throw new \Exception('OpenAI error: ' . $response);
        }

        return $result['data'][0]['embedding']; // This is the 1536-float vector
    }
}
