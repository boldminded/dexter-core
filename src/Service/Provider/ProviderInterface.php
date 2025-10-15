<?php

namespace Litzinger\DexterCore\Service\Provider;

use Litzinger\DexterCore\Service\Options;

interface ProviderInterface
{
    public function __construct(Options $options);

    public function request(string $prompt, string $content, string $requestType): string;
}
