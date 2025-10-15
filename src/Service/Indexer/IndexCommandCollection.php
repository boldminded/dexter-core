<?php

namespace Litzinger\DexterCore\Service\Indexer;

class IndexCommandCollection implements CommandCollection
{
    private array $data = [];

    /**
     * @param IndexCommand[] $commands
     */
    public function __construct(
        array $commands,
    ) {
        $this->data = $commands;
    }

    public function getCommands(): array
    {
        return $this->data;
    }

    public function count(): int
    {
        return count($this->data);
    }
}
