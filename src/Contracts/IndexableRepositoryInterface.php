<?php

namespace BoldMinded\DexterCore\Contracts;

interface IndexableRepositoryInterface
{
    public function find(int $id): ?IndexableInterface;
}
