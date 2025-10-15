<?php

namespace BoldMinded\DexterCore\Service\Indexer;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;

class DeleteUserCommand implements DeleteCommand
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
        // EE is member_, Craft is user_
        $prefix = defined('APP_VER') ? 'member_' : 'user_';

        return $prefix . $this->id;
    }

    public function getQueueJobName(): string
    {
        return $this->queueJobName;
    }
}
