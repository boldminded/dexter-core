<?php

namespace Litzinger\DexterCore\Contracts;

interface CategoryInterface
{
    public function getId(): int;

    public function getName(): string;

    public function getGroupId(): int;



    public function getGroupName(): string;

    public function getParentId(): int;
}
