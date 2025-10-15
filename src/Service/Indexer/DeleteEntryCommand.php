<?php

namespace Litzinger\DexterCore\Service\Indexer;

use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\IndexableInterface;

class DeleteEntryCommand implements DeleteCommand
{
    public function __construct(
        public string $indexName,
        public int|string $id,
        public string $title,
        public string $queueJobName,
    ) {
    }

    public function execute(): bool
    {
        return true;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUniqueId(): string
    {
        return 'entry_' . $this->id;
    }

    public function getQueueJobName(): string
    {
        return $this->queueJobName;
    }
}
