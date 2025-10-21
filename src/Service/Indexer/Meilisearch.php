<?php

namespace BoldMinded\DexterCore\Service\Indexer;

use Meilisearch\Client;
use Meilisearch\Contracts\Index\Settings;
use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;
use BoldMinded\DexterCore\Contracts\QueueInterface;
use BoldMinded\DexterCore\Contracts\TranslatorInterface;

class Meilisearch implements IndexProvider
{
    private Client $client;
    private ConfigInterface $config;
    private LoggerInterface $logger;
    private QueueInterface $queue;
    private TranslatorInterface $translator;
    private bool $shouldUseQueue;

    public function __construct(
        Client $client,
        ConfigInterface $config,
        LoggerInterface $logger,
        QueueInterface $queue,
        TranslatorInterface $translator,
        bool $shouldUseQueue = false
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
        $this->queue = $queue;
        $this->translator = $translator;
        $this->shouldUseQueue = $shouldUseQueue;
    }

    public function single(
        IndexCommand $command,
        bool $shouldQueue = true
    ): IndexerResponse {
        if ($this->shouldUseQueue && $shouldQueue) {
            $this->queue->push($command->getQueueJobName(), $command->getValues());

            return (new IndexerResponse())
                ->setSuccess(true)
                ->setSaved(1)
                ->setMessage($this->translator->get('dexter_msg_body_index_success_queue'));
        }

        $values = [];

        try {
            $values = $command->execute();
            $indexName = $command->getIndexName();

            if (!$indexName) {
                return (new IndexerResponse())->addError($indexName . 'invalid');
            }

            $primaryKey = $this->config->get('primaryKey', $indexName, $values);

            $this->createIndex($indexName);

            if (empty($values)) {
                $this->client->index($indexName)->deleteDocument($command->getUniqueId());

                return (new IndexerResponse())
                    ->setSuccess(false)
                    ->addError(sprintf($this->translator->get('dexter_msg_error_delete'), 'Meilisearch'))
                    ->addError(json_encode($values, true));
            } else {
                $document = array_merge([$primaryKey => $command->getUniqueId()], $values);

                $task = $this->client->index($indexName)->addDocuments([$document], $primaryKey);

                $response = $this->client->waitForTask($task['taskUid']);

                if ($response['status'] === 'succeeded') {
                    return (new IndexerResponse())
                        ->setSuccess(true)
                        ->setSaved(1);
                }

                if ($response['error']) {
                    return (new IndexerResponse())
                        ->setSuccess(false)
                        ->addError($response['error']['message'])
                        ->addError(json_encode($document, true));
                }

                return (new IndexerResponse())->setSaved(0);
            }
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            $response = (new IndexerResponse())
                ->setSuccess(false)
                ->addError($e->getMessage());

            if (!empty($values)) {
                $response->addError(json_encode($values, true));
            }

            return $response;
        }
    }

    public function bulk(CommandCollection $collection): IndexerResponse
    {
        $deleteObjects = [];
        $saveObjects = [];

        if ($this->shouldUseQueue) {
            /** @var IndexCommand $command */
            foreach ($collection->getCommands() as $command) {
                $this->queue->push($command->getQueueJobName(), $command->getValues());
            }

            return (new IndexerResponse())
                ->setSuccess(true)
                ->setSaved($collection->count());
        }

        $indexerResponse = new IndexerResponse();

        /** @var IndexCommand $command */
        foreach ($collection->getCommands() as $command) {
            $values = $command->execute();
            $indexName = $command->getIndexName();

            if (!$indexName) {
                $indexerResponse->addError($indexName . 'invalid');
            }

            if (empty($values)) {
                $deleteObjects[] = $command->getUniqueId();
            } else {
                $saveObjects[] = array_merge([
                    $this->config->get('primaryKey', $indexName, $values) => $command->getUniqueId()
                ], $values);
            }

            try {
                $primaryKey = $this->config->get('primaryKey', $indexName, $values);

                $this->createIndex($indexName);

                if (!empty($deleteObjects)) {
                    $this->client->index($indexName)->deleteDocuments($deleteObjects);
                    $indexerResponse->setDeleted(count($deleteObjects));
                }

                if (!empty($saveObjects)) {
                    $task = $this->client->index($indexName)->addDocuments($saveObjects, $primaryKey);

                    $response = $this->client->waitForTask($task['taskUid']);

                    if ($response['status'] === 'succeeded') {
                        $indexerResponse->setSaved(count($saveObjects));
                    }

                    if ($response['error']) {
                        $indexerResponse->addError($response['error']['message']);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
                $indexerResponse->addError($e->getMessage());
            }
        }

        if (count($indexerResponse->getErrors()) > 0) {
            $indexerResponse->setSuccess(false);
        }

        return $indexerResponse;
    }

    public function delete(
        DeleteCommand $command,
        bool $shouldQueue = true
    ): IndexerResponse {
        try {
            if ($command->getIndexName()) {
                if ($this->shouldUseQueue && $shouldQueue) {
                    $this->queue->push($command->getQueueJobName(), [
                        'uid' => $command->getId(),
                        'title' => $command->getTitle(),
                        'payload' => [
                            'indexName' => $command->getIndexName(),
                            'siteId' => $command->getSiteId(),
                        ],
                    ]);

                    return (new IndexerResponse())
                        ->setSuccess(true)
                        ->setDeleted(1)
                        ->setMessage($this->translator->get('dexter_msg_body_delete_success_queue'));
                }

                $task = $this->client->index($command->getIndexName())->deleteDocument($command->getUniqueId());

                $response = $this->client->waitForTask($task['taskUid']);

                if ($response['status'] === 'succeeded') {
                    return (new IndexerResponse())
                        ->setSuccess(true)
                        ->setDeleted(1);
                }

                if ($response['error']) {
                    return (new IndexerResponse())
                        ->setSuccess(false)
                        ->setDeleted(0)
                        ->addError($response['error']['message']);
                }

                return (new IndexerResponse())->setDeleted(0);
            }
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->setSuccess(false)
                ->addError($e->getMessage());
        }

        return (new IndexerResponse())->setSuccess(false);
    }

    public function clear(string $indexName): IndexerResponse
    {
        try {
            $settings = $this->export($indexName);
            $this->client->index($indexName)->deleteAllDocuments();
            $task = $this->client->index($indexName)->delete();

            $response = $this->client->waitForTask($task['taskUid']);

            if ($response['status'] === 'succeeded') {
                $this->client->createIndex($indexName);
                $this->import($indexName, $settings);

                return (new IndexerResponse())
                    ->setSuccess(true)
                    ->setDeleted(1);
            }

            if ($response['error']) {
                return (new IndexerResponse())
                    ->setSuccess(false)
                    ->setDeleted(0)
                    ->addError($response['error']['message']);
            }

            return (new IndexerResponse());
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->setSuccess(false)
                ->addError($e->getMessage());
        }
    }

    public function deleteIndex(string $indexName): IndexerResponse
    {
        try {
            $task = $this->client->index($indexName)->delete();

            $response = $this->client->waitForTask($task['taskUid']);

            if ($response['status'] === 'succeeded') {
                return (new IndexerResponse())
                    ->setSuccess(true)
                    ->setDeleted(1);
            }

            if ($response['error']) {
                return (new IndexerResponse())
                    ->setSuccess(false)
                    ->setDeleted(0)
                    ->addError($response['error']['message']);
            }

            return (new IndexerResponse());
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->setSuccess(false)
                ->addError($e->getMessage());
        }
    }

    public function list(): array
    {
        $stats = $this->client->stats();
        $collection = [];

        foreach ($stats['indexes'] as $indexName => $indexStats) {
            $collection[] = [
                'indexName' => $indexName,
                'documentCount' => $indexStats['numberOfDocuments'],
                'totalSize' => $indexStats['rawDocumentDbSize'],
                'avgSize' => $indexStats['avgDocumentSize'],
            ];
        }

        return $collection;
    }

    private function createIndex(string $indexName): bool
    {
        try {
            $this->client->getIndex($indexName);
        } catch (\Exception $e) {
            $this->client->createIndex($indexName);
        }

        return true;
    }

    public function export(string $indexName): array
    {
        $settings = $this->client->index($indexName)->getSettings();

        $settings['synonyms'] = (array) $settings['synonyms']->jsonSerialize();
        $settings['typoTolerance'] = (array) $settings['typoTolerance']->jsonSerialize();
        $settings['faceting'] = (array) $settings['faceting']->jsonSerialize();
        $settings['embedders'] = (array) $settings['embedders']->jsonSerialize();

        return $settings;
    }

    public function import(string $indexName, array $settings): bool
    {
        if ($this->config->get('enableContextSearch') === true) {
            $settings['embedders'] = $this->prepareEmbedderSettings();
        }

        $this->createIndex($indexName);
        $task = $this->client->index($indexName)->updateSettings(new Settings($settings));
        $response = $this->client->waitForTask($task['taskUid']);

        return $response['status'] === 'succeeded';
    }

    private function prepareEmbedderSettings(): array
    {
        $provider = $this->config->get('embeddingProvider', 'openAi');

        return [
            'fullText' => [
                'apiKey' => $this->config->get($provider . '.key'),
                'documentTemplate' => "'{{ doc.__full_text }}'",
                'model' => $this->config->get($provider . '.embedModel', 'text-embedding-3-small'),
                'source' => $provider,
            ]
        ];
    }
}
