<?php

namespace Litzinger\DexterCore\Service\Indexer;

interface IndexProvider
{
    public function single(IndexCommand $command, bool $shouldQueue = true): IndexerResponse;

    public function bulk(CommandCollection $collection): IndexerResponse;

    public function delete(DeleteCommand $command, bool $shouldQueue = true): IndexerResponse;

    public function clear(string $indexName): IndexerResponse;

    public function deleteIndex(string $indexName): IndexerResponse;

    public function export(string $indexName): array;

    public function import(string $indexName, array $settings): bool;

    public function list(): array;
}
