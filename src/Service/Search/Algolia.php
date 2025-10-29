<?php

namespace BoldMinded\DexterCore\Service\Search;

use Algolia\AlgoliaSearch\Api\SearchClient as Client;
use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;
use CmsIg\Seal\Adapter\Algolia\AlgoliaSearcher;
use CmsIg\Seal\Schema\Schema as SealSchema;
use CmsIg\Seal\Adapter\SearcherInterface as SealSearcher;
use CmsIg\Seal\Search\SearchBuilder;
use CmsIg\Seal\Search\Condition\Condition;
use CmsIg\Seal\Search\Facet\Facet;

class Algolia implements SearchProvider
{
    private Client $client;
    private ConfigInterface $config;
    private LoggerInterface $logger;
    private ?SealSchema $sealSchema = null;
    private ?SealSearcher $sealSearcher = null;
    private ?string $schemaSettingsDir;

    public function __construct(
        Client $client,
        ConfigInterface $config,
        LoggerInterface $logger,
        ?string $schemaSettingsDir = null,
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
            $results = $this->client->search([
                'requests' => [
                    array_merge([
                        'index' => $index,
                        'query' => $query,
                        'hitsPerPage' => $limit,
                    ], $searchParams),
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
            $weights = $federation['weights'] ?? [];

            foreach ($queries as $i => $q) {
                if (empty($q['indexName'])) {
                    $this->logger->warning("multiSearch: missing indexName at queries[$i]");
                    continue;
                }

                // Allow for flexibility and similarities with Meilisearch
                $q['query'] = $q['q'] ?? $query;
                unset($q['q']);

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
                $indexName = $queries[$i]['indexName'];
                $w = $weights[$indexName] ?? 1.0;

                foreach ($res['hits'] as $rank => $hit) {
                    $hit['_score'] = $w * (1.0 / ($rank + 1));
                    $pool[] = $hit;
                }
            }

            // Sort descending by score limit to requested page size
            usort($pool, fn($a, $b) => $b['_score'] <=> $a['_score']);
            $blended = array_slice($pool, 0, $federation['hitsPerPage'] ?? $limit);

            return $blended;
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

            $builder = new SearchBuilder($this->sealSchema, $this->sealSearcher);
            $builder->index($index);

            if (!empty($filter['q'])) {
                $builder->addFilter(C::search((string)$filter['q']));
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

                // Pass-through Meilisearch-native vector flags for unified JSON.
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
        if ($this->sealSchema instanceof SealSchema || $this->schemaSettingsDir === null) {
            return;
        }
        try {
            $this->sealSchema = SealSchemaLoader::loadAlgolia($this->schemaSettingsDir);
            if (!$this->sealSearcher) {
                $this->sealSearcher = new AlgoliaSearcher($this->client);
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Failed to load SEAL schema from directory: ' . $e->getMessage());
        }
    }
}
