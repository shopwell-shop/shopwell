<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\DataAbstractionLayer\Event;

use OpenSearchDSL\Search;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class ElasticsearchEntitySearcherSearchEvent extends Event implements ShopwellEvent
{
    public function __construct(
        private readonly Search $search,
        private readonly EntityDefinition $definition,
        private readonly Criteria $criteria,
        private readonly Context $context
    ) {
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
