<?php

namespace Litzinger\DexterCore\Service\Search;

use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\LoggerInterface;
use Litzinger\DexterCore\Service\Provider\AIOptions;
use Litzinger\DexterCore\Service\Provider\AIProviderFactory;
use Litzinger\DexterCore\Service\Provider\ProviderInterface;

class Advanced
{
    private ProviderInterface $provider;

    public function __construct(private ConfigInterface $config, private LoggerInterface $logger)
    {
        $providerName = $this->config->get('aiProvider');
        $options = AIOptions::fromArray(array_merge(
            ['provider' => $providerName],
            $this->config->get($providerName),
        ));

        $this->provider = AIProviderFactory::create($options);
    }

    public function search(string $query, array $results): array
    {
        if (empty($query) || empty($results)) {
            return $results;
        }

        try {
            $encodedResults = json_encode(array_column($results, '__full_text', 'objectID'));

            $objectIds = $this->provider->request(
                'Given the following JSON result object where the keys are the objectIDs,
                and value is the result description, return only a comma delimited list of the full objectID key values
                including any text prefix, e.g. entry_ or file_.
                Do not wrap the return results in a code block.
                Refine and possibly reduce the results based on the following query: ' . $query,
                $encodedResults,
            );

            $objectIds = array_map('trim', explode(',', $objectIds));

            // In-case AI flops on the instructions
            $matchingKeys = preg_grep('/^entry_|file_/', $objectIds);
            if (count($matchingKeys) !== count($objectIds)) {
                return $results;
            }

            $filteredResults = array_filter($results, static fn ($result) => in_array($result['objectID'], $objectIds, true));

            return array_values($filteredResults);
        } catch (\Exception $exception) {
            $this->logger->debug($exception->getMessage());
        }

        return [];
    }
}
