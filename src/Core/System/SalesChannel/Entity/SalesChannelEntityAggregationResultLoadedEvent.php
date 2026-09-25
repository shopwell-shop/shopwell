<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelEntityAggregationResultLoadedEvent extends EntityAggregationResultLoadedEvent implements ShopwellSalesChannelEvent
{
    private readonly SalesChannelContext $salesChannelContext;

    public function __construct(
        EntityDefinition $definition,
        AggregationResultCollection $result,
        SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($definition, $result, $salesChannelContext->getContext());
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getName(): string
    {
        return 'sales_channel.' . parent::getName();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
