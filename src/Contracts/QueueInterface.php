<?php

namespace Litzinger\DexterCore\Contracts;

interface QueueInterface
{
    public function push(string $job, array $data): void;
}
