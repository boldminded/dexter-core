<?php

namespace Litzinger\DexterCore\Contracts;

interface QueueableCommand
{
    public function getQueueJobName(): string;
}
