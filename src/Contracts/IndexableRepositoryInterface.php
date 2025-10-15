<?php

namespace Litzinger\DexterCore\Contracts;

interface IndexableRepositoryInterface
{
    public function find(int $id): ?IndexableInterface;
}
