<?php

namespace BoldMinded\DexterCore\Service\Search;

interface SearchProvider
{
    public function getClient();

    public function search(
        string $index,
        string $query = '',
        array|string $searchParams = [],
        int $limit = 100
    ): array;

    public function multiSearch(
        array $queries = [],
        string $query = '',
        array $federation = [],
        int $limit = 100
    ): array;

    /**
     * Execute a search using a common JSON payload built from a Twig template.
     * Expected keys (subset): index, q, filters, sort, limit, offset, highlight, distinct, facets.
     */
    public function searchFromJson(array $filter): array;

    /**
     * Execute a multi-index search using a common JSON payload.
     * Expected keys: query, queries[], federation (limit, offset, weights, ...).
     */
    public function multiSearchFromJson(array $payload): array;
}
