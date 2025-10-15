<?php

namespace BoldMinded\DexterCore\Service\Indexer;

interface ReIndexCommands
{
    public function getCommandCollection(): IndexCommandCollection;

    public function getAlerts(): array;

    public function getIndexName(): string;
}
