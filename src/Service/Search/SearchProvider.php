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
}
