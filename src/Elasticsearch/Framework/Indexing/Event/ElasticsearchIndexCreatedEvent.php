<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\Indexing\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('framework')]
class ElasticsearchIndexCreatedEvent
{
    public function __construct(
        private readonly string $indexName,
        private readonly AbstractElasticsearchDefinition $definition
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getDefinition(): AbstractElasticsearchDefinition
    {
        return $this->definition;
    }
}
