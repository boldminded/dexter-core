<?php

namespace BoldMinded\DexterCore\Service\Search;

interface SearchProvider
{
    public function getClient();

    public function search(
        string $index,
        string $query = '',
        array|string $filter = [],
        int $perPage = 50
    ): array;

    public function multiSearch(
        array $queries = [],
    ): array;
}
