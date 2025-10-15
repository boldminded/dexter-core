<?php

namespace BoldMinded\DexterCore\Contracts;

interface QueueableCommand
{
    public function getQueueJobName(): string;
}
