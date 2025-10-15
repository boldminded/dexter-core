<?php

namespace BoldMinded\DexterCore\Service\Indexer;

interface CommandCollection
{
    public function getCommands(): array;

    public function count(): int;
}
