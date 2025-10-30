<?php

namespace BoldMinded\DexterCore\Service\Search;

use Meilisearch\Client;
use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;

class Meilisearch implements SearchProvider
{
    private Client $client;
    private ConfigInterface $config;
    private LoggerInterface $logger;

    public function __construct(
        Client $client,
        ConfigInterface $config,
        LoggerInterface $logger,
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
            $index = $this->client->index($index);
            $results = $index->search($query, $searchParams);

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

    public function multiSearch(
        array $queries = [],
        string $query = '',
        array $federation = [],
        int $limit = 100
    ): array {
        try {
            $searchQueries = [];

            foreach ($queries as $i => $q) {
                $indexName = Normalizer::indexName($q);

                if ($indexName === '') {
                    $this->logger->warning("multiSearch: missing index, indexName, or indexUid at queries[$i]");
                    continue;
                }

                $sq = new SearchQuery();
                $sq->setIndexUid($indexName);

                // If query/term is not set on each query, use the shared one
                $sq->setQuery((string) ($q['q'] ?? $query));

                // Optional params commonly used. Add more as you need.
                if (isset($q['filter']))                { $sq->setFilter($q['filter']); }
                if (isset($q['limit']))                 { $sq->setLimit((int) $q['limit']); }
                if (isset($q['offset']))                { $sq->setOffset((int) $q['offset']); }
                if (isset($q['hitsPerPage']))           { $sq->setHitsPerPage((int) $q['hitsPerPage']); }
                if (isset($q['page']))                  { $sq->setPage((int) $q['page']); }
                if (isset($q['attributesToRetrieve']))  { $sq->setAttributesToRetrieve($q['attributesToRetrieve']); }
                if (isset($q['attributesToHighlight'])) { $sq->setAttributesToHighlight($q['attributesToHighlight']); }
                if (isset($q['facets']))                { $sq->setFacets($q['facets']); }
                if (isset($q['sort']))                  { $sq->setSort($q['sort']); }
                if (isset($q['matchingStrategy']))      { $sq->setMatchingStrategy($q['matchingStrategy']); }
                if (isset($q['showRankingScore']))      { $sq->setShowRankingScore((bool) $q['showRankingScore']); }
                if (isset($q['showMatchesPosition']))   { $sq->setShowMatchesPosition((bool) $q['showMatchesPosition']); }

                // Forward vector options if supported by the installed SDK version.
                if (isset($q['retrieveVectors']) && method_exists($sq, 'setRetrieveVectors')) {
                    $sq->setRetrieveVectors((bool) $q['retrieveVectors']);
                }
                if (isset($q['vector']) && method_exists($sq, 'setVector')) {
                    $sq->setVector($q['vector']); // expects numeric array
                }

                $searchQueries[] = $sq;
            }

            if ($searchQueries === []) {
                return [];
            }

            $fed = null;
            $hasFed = array_intersect(array_keys($federation), ['limit','offset','facetsByIndex','mergeFacets']) !== [];

            if ($hasFed) {
                $fed = new MultiSearchFederation();
                if (isset($federation['limit']))         { $fed->setLimit((int) $federation['limit']); }
                if (isset($federation['offset']))        { $fed->setOffset((int) $federation['offset']); }
                if (isset($federation['facetsByIndex'])) { $fed->setFacetsByIndex($federation['facetsByIndex']); }
                if (isset($federation['mergeFacets']))   { $fed->setMergeFacets($federation['mergeFacets']); }
            }

            $results = $this->client->multiSearch($searchQueries, $fed);

            return $results['hits'] ?? [];
        } catch (\Throwable $exception) {
            $this->logger->debug($exception->getMessage());
            return [];
        }
    }
}
