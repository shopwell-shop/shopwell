<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping\SalesChannel;

use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SalesChannelShippingMethodDefinition extends ShippingMethodDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannelId()));
    }
}
