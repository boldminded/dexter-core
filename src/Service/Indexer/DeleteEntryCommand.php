<?php

namespace BoldMinded\DexterCore\Service\Indexer;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;

class DeleteEntryCommand implements DeleteCommand
{
    public function __construct(
        public string $indexName,
        public int|string $id,
        public int $siteId,
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

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUniqueId(): string
    {
        return 'entry_' . $this->siteId . '_' . $this->id;
    }

    public function getQueueJobName(): string
    {
        return $this->queueJobName;
    }
}
