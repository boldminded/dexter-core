<?php

namespace BoldMinded\DexterCore\Service\Indexer;

use Algolia\AlgoliaSearch\Api\SearchClient as Client;
use BoldMinded\DexterCore\Contracts\ConfigInterface;
use BoldMinded\DexterCore\Contracts\LoggerInterface;
use BoldMinded\DexterCore\Contracts\QueueInterface;
use BoldMinded\DexterCore\Contracts\TranslatorInterface;

class Algolia implements IndexProvider
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
                ->setSaved(1)
                ->setSuccess(true)
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

            if (empty($values)) {
                $this->client->deleteObject($indexName, $command->getUniqueId());

                return (new IndexerResponse())
                    ->setSuccess(false)
                    ->addError(sprintf($this->translator->get('dexter_msg_error_delete'), 'Algolia'))
                    ->addError(json_encode($values, true));
            } else {
                $document = array_merge([$primaryKey => $command->getUniqueId()], $values);

                $this->client->saveObject(
                    $indexName,
                    $document
                );

                return (new IndexerResponse())
                    ->setSuccess(true)
                    ->setSaved(1);
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

        // Documents are grouped by index name here and sent once per index
        // after the loop.
        //
        // Sending inside the loop re-sent every document accumulated so far on
        // every iteration -- n(n+1)/2 document writes for n commands -- and
        // sent them to whichever index the current command happened to name,
        // which is wrong as soon as one collection spans several indexes.
        /** @var IndexCommand $command */
        foreach ($collection->getCommands() as $command) {
            $values = $command->execute();
            $indexName = $command->getIndexName();

            if (!$indexName) {
                $indexerResponse->addError($indexName . 'invalid');

                continue;
            }

            if (empty($values)) {
                $deleteObjects[$indexName][] = $command->getUniqueId();
            } else {
                $saveObjects[$indexName][] = array_merge([
                    $this->config->get('primaryKey', $indexName, $values) => $command->getUniqueId()
                ], $values);
            }
        }

        $saved = 0;
        $deleted = 0;

        foreach (array_keys($saveObjects + $deleteObjects) as $indexName) {
            try {
                // Deletes first, so a document removed by one command and
                // re-saved by a later one in the same collection ends up
                // saved rather than deleted by an ordering accident.
                if (!empty($deleteObjects[$indexName])) {
                    $this->client->deleteObjects($indexName, $deleteObjects[$indexName]);
                    $deleted += count($deleteObjects[$indexName]);
                }

                if (!empty($saveObjects[$indexName])) {
                    $this->client->saveObjects($indexName, $saveObjects[$indexName]);
                    $saved += count($saveObjects[$indexName]);
                }
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());

                $indexerResponse
                    ->setSuccess(false)
                    ->addError($e->getMessage());
            }
        }

        $indexerResponse->setSaved($saved)->setDeleted($deleted);

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

                $this->client->deleteObject($command->getIndexName(), $command->getUniqueId());

                return (new IndexerResponse())
                    ->setSuccess(true)
                    ->setDeleted(1);
            }
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->setSuccess(false)
                ->setDeleted(0)
                ->addError($e->getMessage());
        }
    }

    public function clear(string $indexName): IndexerResponse
    {
        try {
            $this->client->clearObjects($indexName);

            return (new IndexerResponse());
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->addError($e->getMessage())
                ->setSuccess(false);
        }
    }

    public function deleteIndex(string $indexName): IndexerResponse
    {
        try {
            $this->client->deleteIndex($indexName);

            return (new IndexerResponse())
                ->setSuccess(true)
                ->setDeleted(1);
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            return (new IndexerResponse())
                ->setSuccess(false)
                ->addError($e->getMessage());
        }
    }

    public function list(): array
    {
        $indices = $this->client->listIndices();
        $indices = $indices['items'] ?? [];

        if (empty($indices)) {
            return [];
        }

        $collection = [];

        foreach ($indices as $index) {
            $collection[] = [
                'indexName' => $index['name'],
                'documentCount' => $index['entries'],
                'totalSize' => $index['dataSize'],
                'avgSize' => $index['entries'] > 0 ? round($index['dataSize'] / $index['entries']) : 0,
            ];
        }

        return $collection;
    }

    public function export(string $indexName): array
    {
        $settings = $this->client->getSettings($indexName);
        $rules = [];
        $synonyms = [];

        return [
            'settings' => $settings,
            'rules' => $rules,
            'synonyms' => $synonyms,
        ];
    }

    public function import(string $indexName, array $settings): bool
    {
        $basicSettings = $settings['settings'] ?? [];

        if ($this->config->get('enableSemanticSearch') === true) {
            $basicSettings['mode'] = 'neuralSearch';
        } else {
            $basicSettings['mode'] = $this->config->get('algolia.mode') ?: 'keywordSearch';
        }

        if (!empty($basicSettings)) {
            $this->client->setSettings($indexName, $basicSettings);
            return true;
        }

        return false;
    }
}
