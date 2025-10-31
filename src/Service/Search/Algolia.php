<?php

namespace BoldMinded\DexterCore\Service\Search;

use Algolia\AlgoliaSearch\Api\SearchClient as Client;
use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;

class Algolia implements SearchProvider
{
    private Client $client;
    private ConfigInterface $config;
    private LoggerInterface $logger;

    public function __construct(
        Client $client,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function search(
        string $index,
        string $query = '',
        array|string $searchParams = [],
        int $limit = 100
    ): array {
        try {
            $results = $this->client->search([
                'requests' => [
                    array_merge([
                        'indexName' => $index,
                        'query' => $query,
                        'hitsPerPage' => $limit,
                    ], $searchParams),
                ],
            ]);

            $hits = $results['results'][0]['hits'] ?? [];

            $hits = $this->filterByRankingScore($hits, $searchParams);

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

    public function multiSearch(
        array $queries = [],
        string $query = '',
        array $federation = [],
        int $limit = 100
    ): array {
        try {
            $searchQueries = [];

            $federation['hitsPerPage'] ??= $federation['limit'] ?? $limit;
            unset($federation['limit']);

            foreach ($queries as $i => $q) {
                $indexName = Normalizer::indexName($q);
                $q['indexName'] = $indexName;
                unset($q['index'], $q['indexUid']);

                if (!$indexName) {
                    $this->logger->warning("multiSearch: missing indexName at queries[$i]");
                    continue;
                }

                // Allow for flexibility and similarities with Meilisearch
                $q['query'] = Normalizer::searchQuery($q);
                unset($q['q'], $q['term']);

                // Algolia doesn't have a federation feature, but we're going to do something similar.
                // Use the fed array as an option to apply the same settings to all queries without having to repeat them.
                $searchQueries[] = array_merge(
                    $q,
                    $federation,
                );
            }

            if ($searchQueries === []) {
                return [];
            }

            $results = $this->client->search([
                'requests' => $searchQueries,
            ]);

            // Build a flat pool with a naive score = weight * 1/(rank+1)
            $pool = [];
            foreach ($results['results'] as $i => $res) {
                $indexName = Normalizer::indexName($queries[$i]);
                $w = $weights[$indexName] ?? 1.0;

                foreach ($res['hits'] as $rank => $hit) {
                    $hit['_score'] = $w * (1.0 / ($rank + 1));
                    $pool[] = $hit;
                }
            }

            // Sort descending by score limit to requested page size
            usort($pool, fn($a, $b) => $b['_score'] <=> $a['_score']);
            $blended = array_slice($pool, 0, $federation['hitsPerPage'] ?? $limit);

            $blended = $this->filterByRankingScore($blended, $federation);

            return $blended;
        } catch (\Throwable $exception) {
            $this->logger->debug($exception->getMessage());
            return [];
        }
    }

    private function filterByRankingScore(array $hits, array $searchParams = []): array
    {
        $minScore = $this->config->get('minimumRankingScore') ?? 0;
        $showRankingScore = Normalizer::rankingScore($searchParams);

        if ($showRankingScore && $minScore > 0) {
            $hits = array_values(array_filter(
                $hits,
                fn($h) => ($h['_rankingInfo']['neuralScore'] ?? 0) >= $minScore
            ));
        }

        return $hits;
    }
}
