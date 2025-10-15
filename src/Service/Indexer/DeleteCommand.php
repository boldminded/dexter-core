<?php

namespace Litzinger\DexterCore\Service\Indexer;

use Litzinger\DexterCore\Contracts\QueueableCommand;

interface DeleteCommand extends QueueableCommand
{
    public function execute(): bool;

    public function getId(): int|string;

    public function getUniqueId(): string;

    public function getIndexName(): string;

    public function getTitle(): string;

    public function getQueueJobName(): string;
}

