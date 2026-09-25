<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group;

use Shopwell\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface LineItemGroupRuleMatcherInterface
{
    /**
     * Gets a list of line items that match for the provided group object.
     * You can use AND conditions, OR conditions, or anything else, depending on your implementation.
     */
    public function getMatchingItems(LineItemGroupDefinition $groupDefinition, LineItemFlatCollection $items, SalesChannelContext $context): LineItemFlatCollection;
}
