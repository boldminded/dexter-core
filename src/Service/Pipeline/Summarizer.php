<?php

declare(strict_types=1);

namespace BoldMinded\DexterCore\Service\Pipeline;

use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\IndexableInterface;
use BoldMinded\DexterCore\Service\Provider\AIOptions;
use BoldMinded\DexterCore\Service\Provider\AIProviderFactory;

class Summarizer
{
    public function __construct(
        private IndexableInterface $indexable,
        private ConfigInterface    $config
    ) {
    }

    public function __invoke(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $flatString = $this->flatten($values);
        $provider = AIProviderFactory::create($this->buildOptions());

        $values['summary'] = $provider->request($flatString, 'document');

        // @todo might need to refactor this into a service class and move the
        // pipeline itself to the EE and Craft repositories due to differences
        // in queue and saving the indexable back to the respective dbs.
        //if ($useQueue) {
            //$queue = QueueFactory::create(Craft::$app->getQueue());
            //$queue->push(UpdateFileJob::class, [
            //    'uid' => $this->indexable->getId(),
            //    'title' => $entity->title,
            //    'payload' => $values,
            //]);
            //
            //return $values;
        //}

        //(new FileUpdater($this->indexable, $this->config))->update([
        //    'uid' => $this->indexable->getId(),
        //    'title' => $entity->title,
        //    'payload' => $values,
        //]);

        return $values;
    }

    private function flatten(array $array): string
    {
        $flat = [];

        array_walk_recursive($array, function ($value, $key) use (&$flat) {
            if (
                !in_array($key, ['id', 'uid'], true)
                && $value
                && is_scalar($value)
                && !is_numeric($value)
            ) {
                $flat[] = (string) $value;
            }
        });

        return strip_tags(implode(' ', array_unique($flat)));
    }

    private function buildOptions(): AIOptions
    {
        $provider = $this->config->get('aiProvider', 'openAi');

        return new AIOptions(
            provider: $provider,
            prompt: $this->config->get('summarizePrompt') ?: 'Summarize this text into a short description',
            key: $this->config->get($provider . '.key'),
            model: $this->config->get($provider . '.model'),
            embedModel: $this->config->get($provider . '.embedModel'),
            temperature: $this->config->get($provider . '.temperature'),
            frequencyPenalty: $this->config->get($provider . '.frequencyPenalty'),
            presencePenalty: $this->config->get($provider . '.presencePenalty'),
            maxTokens: $this->config->get($provider . '.maxTokens'),
        );
    }
}
