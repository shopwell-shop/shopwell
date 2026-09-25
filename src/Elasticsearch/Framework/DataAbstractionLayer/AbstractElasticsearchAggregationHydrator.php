<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractElasticsearchAggregationHydrator
{
    abstract public function getDecorated(): AbstractElasticsearchAggregationHydrator;

    abstract public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): AggregationResultCollection;
}
