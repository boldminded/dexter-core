<?php

namespace Litzinger\DexterCore\Service\Search;

use Algolia\AlgoliaSearch\Api\SearchClient as Client;
use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\LoggerInterface;

class Algolia implements SearchProvider
{
    private Client $client;
    private ConfigInterface $config;
    private LoggerInterface $logger;

    public function __construct(Client $client, ConfigInterface $config, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function search(
        string $index,
        string $query = '',
        array|string $filter = [],
        int $perPage = 50
    ): array {
        try {
            $results = $this->client->search([
                'requests' => [
                    [
                        'indexName' => $index,
                        'query' => $query,
                        'hitsPerPage' => $perPage,
                        'filters' => $filter,
                    ],
                ],
            ]);

            $hits = $results['results'][0]['hits'] ?? [];

            if ($this->config->get('enableAdvancedSearch') === true) {
                $filteredHits = (new Advanced($this->config, $this->logger))->search(
                    $query,
                    $hits
                );

                return $filteredHits;
            }

            return $hits;
        } catch (\Throwable $exception) {
            $this->logger->debug($exception->getMessage());
        }

        return [];
    }

    public function getClient()
    {
        return $this->client;
    }
}
