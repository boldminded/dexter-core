<?php

namespace Litzinger\DexterCore\Service\Indexer;

use League\Pipeline\PipelineBuilder;
use Litzinger\DexterCore\Contracts\ConfigInterface;
use Litzinger\DexterCore\Contracts\IndexableInterface;

class IndexCategoryCommand implements IndexCommand
{
    public function __construct(
        public string $indexName,
        public IndexableInterface $indexable,
        public ConfigInterface $config,
        public array $pipelines,
        public string $queueJobName,
    ) {
    }

    public function execute(): array
    {
        $pipelineBuilder = new PipelineBuilder;

        foreach ($this->pipelines as $pipelineClass) {
            $pipelineBuilder->add(
                new $pipelineClass(
                    $this->indexable,
                    $this->config,
                )
            );
        }

        $pipelines = $pipelineBuilder->build();

        return $pipelines->process($this->getValues());
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getValues(): array
    {
        return $this->indexable->getValues();
    }

    public function getId(): int
    {
        return $this->indexable->getId();
    }

    public function getUniqueId(): string
    {
        return 'category_' . $this->indexable->getId();
    }

    public function getQueueJobName(): string
    {
        return $this->queueJobName;
    }
}
