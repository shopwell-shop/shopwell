<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher;

use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractAnyRuleLineItemMatcher
{
    abstract public function getDecorated(): AbstractAnyRuleLineItemMatcher;

    abstract public function isMatching(LineItemGroupDefinition $groupDefinition, LineItem $item, SalesChannelContext $context): bool;
}
