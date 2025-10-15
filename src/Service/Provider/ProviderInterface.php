<?php

namespace BoldMinded\DexterCore\Service\Provider;

use BoldMinded\DexterCore\Service\Options;

interface ProviderInterface
{
    public function __construct(Options $options);

    public function request(string $prompt, string $content, string $requestType): string;
}
