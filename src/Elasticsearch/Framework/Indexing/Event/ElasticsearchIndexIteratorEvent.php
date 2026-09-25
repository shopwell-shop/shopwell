<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\Indexing\Event;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('framework')]
class ElasticsearchIndexIteratorEvent
{
    public function __construct(
        public readonly AbstractElasticsearchDefinition $elasticsearchDefinition,
        public IterableQuery $iterator,
    ) {
    }
}
