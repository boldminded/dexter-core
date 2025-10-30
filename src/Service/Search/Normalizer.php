<?php

namespace BoldMinded\DexterCore\Service\Search;

final readonly class Normalizer
{
    public static function indexName(array $params): string
    {
        return $params['index'] ?? $params['indexName'] ?? $params['indexUid'] ?? '';
    }

    public static function searchQuery(array $params): string
    {
        return $params['term'] ?? $params['query'] ?? $params['q'] ?? '';
    }
}
