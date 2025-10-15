<?php

namespace Litzinger\DexterCore\Service\Search;

interface SearchProvider
{
    public function search(
        string $index,
        string $query = '',
        array|string $filter = [],
        int $perPage = 50
    ): array;

    public function getClient();
}
