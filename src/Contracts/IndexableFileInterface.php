<?php

namespace BoldMinded\DexterCore\Contracts;

interface IndexableFileInterface
{
    public function getMimeType(): string;

    public function getAbsoluteUrl(): string;

    public function getAbsolutePath(): string;

    public function isImage(): bool;
}
