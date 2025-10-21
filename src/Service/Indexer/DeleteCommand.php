<?php

namespace BoldMinded\DexterCore\Service\Indexer;

use BoldMinded\DexterCore\Contracts\QueueableCommand;

interface DeleteCommand extends QueueableCommand
{
    public function execute(): bool;

    public function getId(): int|string;

    public function getSiteId(): int;

    public function getUniqueId(): string;

    public function getIndexName(): string;

    public function getTitle(): string;

    public function getQueueJobName(): string;
}

