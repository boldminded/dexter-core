<?php

namespace Litzinger\DexterCore\Service;

class ConfigFile
{
    private string $basePath = '';

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function get(string $fileName): array
    {
        $fullPath = $this->basePath . $fileName . '.php';

        if (!file_exists($fullPath)) {
            return [];
        }

        return include $fullPath;
    }
}
