<?php

namespace Litzinger\DexterCore\Service\Search;

use Meilisearch\Client;
use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\LoggerInterface;

class Meilisearch implements SearchProvider
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
            $index = $this->client->index($index);
            $results = $index->search($query, $filter);

            $hits = $results->getHits();

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
