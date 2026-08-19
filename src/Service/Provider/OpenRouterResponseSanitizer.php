<?php

namespace BoldMinded\DexterCore\Service\Provider;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * PSR-18 decorator that makes OpenRouter responses safe for openai-php to
 * deserialize.
 *
 * openai-php models `usage.completion_tokens_details` with non-nullable int
 * properties and reads `accepted_prediction_tokens` / `rejected_prediction_tokens`
 * without a null coalesce. Those keys are OpenAI-specific: OpenRouter omits them
 * (and, depending on the upstream model, may omit `reasoning_tokens` too), which
 * throws a TypeError *after* the request has already been billed.
 *
 * Rather than guess at defaults for a token breakdown we do not use, drop the
 * offending sub-objects entirely. `usage.total_tokens` is untouched, so token
 * accounting still works.
 */
class OpenRouterResponseSanitizer implements ClientInterface
{
    public function __construct(
        private ClientInterface $client,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->client->sendRequest($request);

        // Streamed completions arrive as SSE chunks, not a single JSON document.
        // Leave them alone: openai-php parses those through a different path.
        if (!str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {
            return $response;
        }

        $body = (string) $response->getBody();

        if ($body === '') {
            return $response;
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return $response;
        }

        if (!$this->needsSanitizing($decoded)) {
            return $response;
        }

        unset(
            $decoded['usage']['completion_tokens_details'],
            $decoded['usage']['prompt_tokens_details'],
        );

        $sanitized = json_encode($decoded);

        if ($sanitized === false) {
            return $response;
        }

        // Rewind so the consumer reads from the start.
        return $response->withBody($this->streamFactory->createStream($sanitized));
    }

    /**
     * Only rewrite the body when a details object is actually missing a key
     * openai-php requires, so well-formed responses pass through untouched.
     */
    private function needsSanitizing(array $decoded): bool
    {
        $required = [
            'completion_tokens_details' => ['reasoning_tokens', 'accepted_prediction_tokens', 'rejected_prediction_tokens'],
            'prompt_tokens_details' => ['cached_tokens'],
        ];

        foreach ($required as $key => $requiredKeys) {
            $details = $decoded['usage'][$key] ?? null;

            if (!is_array($details)) {
                continue;
            }

            foreach ($requiredKeys as $requiredKey) {
                if (!array_key_exists($requiredKey, $details)) {
                    return true;
                }
            }
        }

        return false;
    }
}
