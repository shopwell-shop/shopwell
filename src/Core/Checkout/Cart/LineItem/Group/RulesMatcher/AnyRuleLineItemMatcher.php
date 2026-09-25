<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group\RulesMatcher;

use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AnyRuleLineItemMatcher extends AbstractAnyRuleLineItemMatcher
{
    public function getDecorated(): AbstractAnyRuleLineItemMatcher
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Gets if the provided line item is allowed for any of the applied
     * rules within the group entity.
     */
    public function isMatching(LineItemGroupDefinition $groupDefinition, LineItem $item, SalesChannelContext $context): bool
    {
        // no rules mean OK
        if ($groupDefinition->getRules()->count() <= 0) {
            return true;
        }

        // if we have rules, make sure
        // they are connected using an OR condition
        $scope = new LineItemScope($item, $context);

        foreach ($groupDefinition->getRules() as $rule) {
            $rootCondition = $rule->getPayload();

            // if any rule matches, return OK
            if ($rootCondition instanceof Rule && $rootCondition->match($scope)) {
                return true;
            }
        }

        return false;
    }
}
