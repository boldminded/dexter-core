<?php

namespace BoldMinded\DexterCore\Service\Search;

use CmsIg\Seal\Adapter\Meilisearch\MeilisearchSearcher;
use CmsIg\Seal\Adapter\SearcherInterface;
use CmsIg\Seal\Schema\Schema;
use CmsIg\Seal\Search\Condition\Condition;
use CmsIg\Seal\Search\Facet\Facet;
use CmsIg\Seal\Search\SearchBuilder;
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
    private ?Schema $sealSchema = null;
    private ?SearcherInterface $sealSearcher = null;
    private ?string $schemaSettingsDir;

    public function __construct(
        Client $client,
        ConfigInterface $config,
        LoggerInterface $logger,
        ?string $schemaSettingsDir = null
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
        $this->schemaSettingsDir = $schemaSettingsDir;
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
                // indexUid is official param name, make indexName optional for cross functionality with Algolia
                $indexName = $q['indexName'] ?? $q['indexUid'] ?? '';

                if ($indexName === '') {
                    $this->logger->warning("multiSearch: missing indexUid at queries[$i]");
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

    public function searchFromJson(array $filter): array
    {
        try {
            $this->ensureSchemaLoaded();
            if (!$this->sealSchema || !$this->sealSearcher) {
                $this->logger->warning('searchFromJson: SEAL components not available');
                return [];
            }

            $index = (string)($filter['index'] ?? '');
            if ($index === '') {
                $this->logger->warning('searchFromJson: missing index');
                return [];
            }

            // Native fallback for Meilisearch-specific parameters (e.g., retrieveVectors, vector, raw filter string)
            if (isset($filter['retrieveVectors']) || isset($filter['vector']) || isset($filter['filter'])) {
                $idx = $this->client->index($index);
                $q = (string)($filter['q'] ?? '');
                $params = [];

                foreach (['filter','limit','offset','hitsPerPage','page','attributesToRetrieve','attributesToHighlight','facets','sort','matchingStrategy','showRankingScore','showMatchesPosition','highlightPreTag','highlightPostTag'] as $k) {
                    if (isset($filter[$k])) { $params[$k] = $filter[$k]; }
                }
                if (isset($filter['retrieveVectors'])) { $params['retrieveVectors'] = (bool)$filter['retrieveVectors']; }
                if (isset($filter['vector'])) { $params['vector'] = $filter['vector']; }

                $results = $idx->search($q, $params);
                $hits = $results->getHits();

                if ($this->config->get('enableAdvancedSearch') === true && !empty($q)) {
                    return (new Advanced($this->config, $this->logger))->search($q, $hits);
                }

                return $hits;
            }

            $builder = new SearchBuilder($this->sealSchema, $this->sealSearcher);
            $builder->index($index);

            if (!empty($filter['q'])) {
                $builder->addFilter(Condition::search((string)$filter['q']));
            }

            foreach ($this->buildConditions($filter['filters'] ?? []) as $cond) {
                $builder->addFilter($cond);
            }

            if (!empty($filter['sort']) && is_array($filter['sort'])) {
                foreach ($filter['sort'] as $field => $dir) {
                    $builder->addSortBy((string)$field, ($dir === 'asc') ? 'asc' : 'desc');
                }
            }

            if (isset($filter['limit'])) { $builder->limit((int)$filter['limit']); }
            if (isset($filter['offset'])) { $builder->offset((int)$filter['offset']); }

            if (!empty($filter['highlight']['fields'])) {
                $pre = $filter['highlight']['preTag'] ?? '<mark>';
                $post = $filter['highlight']['postTag'] ?? '</mark>';
                $builder->highlight($filter['highlight']['fields'], $pre, $post);
            }

            if (!empty($filter['distinct'])) {
                $builder->distinct((string)$filter['distinct']);
            }

            foreach (($filter['facets'] ?? []) as $f) {
                if (($f['type'] ?? '') === 'count') {
                    $builder->addFacet(Facet::count((string)$f['field'], (array)($f['options'] ?? [])));
                } elseif (($f['type'] ?? '') === 'minmax') {
                    $builder->addFacet(Facet::minMax((string)$f['field'], (array)($f['options'] ?? [])));
                }
            }

            $result = $builder->getResult();
            $hits = iterator_to_array($result, false);

            if ($this->config->get('enableAdvancedSearch') === true && !empty($filter['q'])) {
                return (new Advanced($this->config, $this->logger))->search((string)$filter['q'], $hits);
            }

            return $hits;
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            return [];
        }
    }

    public function multiSearchFromJson(array $payload): array
    {
        try {
            $this->ensureSchemaLoaded();
            $sharedQ = (string)($payload['query'] ?? '');
            $queries = (array)($payload['queries'] ?? []);
            if ($queries === []) {
                $this->logger->warning('multiSearchFromJson: missing queries');
                return [];
            }

            $fed = (array)($payload['federation'] ?? []);
            $limit = (int)($fed['limit'] ?? 100);
            $weights = (array)($fed['weights'] ?? []);

            $pool = [];
            foreach ($queries as $i => $q) {
                $index = (string)($q['index'] ?? $q['indexName'] ?? $q['indexUid'] ?? '');
                if ($index === '') {
                    $this->logger->warning("multiSearchFromJson: missing index at queries[$i]");
                    continue;
                }

                $single = [
                    'index' => $index,
                    'q' => (string)($q['q'] ?? $sharedQ),
                    'filters' => $q['filters'] ?? ($q['filter'] ?? []),
                    'sort' => $q['sort'] ?? [],
                    'limit' => (int)($q['limit'] ?? $q['hitsPerPage'] ?? $limit),
                    'offset' => (int)($q['offset'] ?? 0),
                    'highlight' => $q['highlight'] ?? [],
                    'distinct' => $q['distinct'] ?? null,
                    'facets' => $q['facets'] ?? [],
                ];

                // Vector options supported by Meilisearch when available.
                if (isset($q['retrieveVectors'])) { $single['retrieveVectors'] = (bool)$q['retrieveVectors']; }
                if (isset($q['vector'])) { $single['vector'] = $q['vector']; }

                $hits = $this->searchFromJson($single);
                $w = (float)($weights[$index] ?? 1.0);

                foreach ($hits as $rank => $hit) {
                    $hit['_score'] = $w * (1.0 / ($rank + 1));
                    $pool[] = $hit;
                }
            }

            usort($pool, fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
            return array_slice($pool, 0, $limit);
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            return [];
        }
    }

    /** @return array<object> */
    private function buildConditions(array $filters): array
    {
        $out = [];
        foreach ($filters as $f) {
            $type = (string)($f['type'] ?? '');
            $field = $f['field'] ?? null;

            $out[] = match ($type) {
                'search' => Condition::search((string)$f['query']),
                'identifier' => Condition::identifier((string)$f['id']),
                'equal' => Condition::equal((string)$field, $f['value']),
                'not_equal' => Condition::notEqual((string)$field, $f['value']),
                'gt' => Condition::greaterThan((string)$field, $f['value']),
                'gte' => Condition::greaterThanEqual((string)$field, $f['value']),
                'lt' => Condition::lessThan((string)$field, $f['value']),
                'lte' => Condition::lessThanEqual((string)$field, $f['value']),
                'in' => Condition::in((string)$field, (array)$f['values']),
                'not_in' => Condition::notIn((string)$field, (array)$f['values']),
                'geo_distance' => Condition::geoDistance((string)($field ?? '_geo'), (float)$f['lat'], (float)$f['lng'], (int)$f['distance']),
                'geo_bounding_box' => Condition::geoBoundingBox((string)($field ?? '_geo'), (float)$f['north'], (float)$f['east'], (float)$f['south'], (float)$f['west']),
                'and' => Condition::and(...$this->buildConditions((array)($f['conditions'] ?? []))),
                'or' => Condition::or(...$this->buildConditions((array)($f['conditions'] ?? []))),
                default => throw new \LogicException("Unknown filter type: $type"),
            };
        }
        return $out;
    }

    private function ensureSchemaLoaded(): void
    {
        if ($this->sealSchema instanceof Schema || $this->schemaSettingsDir === null) {
            return;
        }
        try {
            $this->sealSchema = SealSchemaLoader::loadMeilisearch($this->schemaSettingsDir);
            if (!$this->sealSearcher) {
                $this->sealSearcher = new MeilisearchSearcher($this->client);
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Failed to load SEAL schema from directory: ' . $e->getMessage());
        }
    }
}
