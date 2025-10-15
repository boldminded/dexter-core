<?php

namespace Litzinger\DexterCore\Service\Indexer;

class Dummy implements IndexProvider
{
    public function single(IndexCommand $command, bool $shouldQueue = true): IndexerResponse
    {
        return (new IndexerResponse())
            ->addError('Whoops, no search provider defined.')
            ->setSuccess(false);
    }

    public function bulk(CommandCollection $collection): IndexerResponse
    {
        return (new IndexerResponse())
            ->addError('Whoops, no search provider defined.')
            ->setSuccess(false);
    }

    public function delete(DeleteCommand $command, bool $shouldQueue = true): IndexerResponse
    {
        return (new IndexerResponse())
            ->addError('Whoops, no search provider defined.')
            ->setSuccess(false);
    }

    public function clear(string $indexName): IndexerResponse
    {
        return (new IndexerResponse())
            ->addError('Whoops, no search provider defined.')
            ->setSuccess(false);
    }

    public function deleteIndex(string $indexName): IndexerResponse
    {
        return (new IndexerResponse())
            ->addError('Whoops, no search provider defined.')
            ->setSuccess(false);
    }

    public function export(string $indexName): array
    {
        return [];
    }

    public function import(string $indexName, array $settings): bool
    {
        return true;
    }

    public function list(): array
    {
        return [];
    }
}
