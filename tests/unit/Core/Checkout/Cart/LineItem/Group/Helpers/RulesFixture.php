<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers;

use Shopwell\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\AndRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
final class RulesFixture
{
    /**
     * Build a rule entity with the provided rule
     * inside the payload property.
     */
    public static function buildRuleEntity(Rule $rule): RuleEntity
    {
        $rules = new AndRule(
            [
                $rule,
            ]
        );

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload($rules);

        return $ruleEntity;
    }

    /**
     * Gets a minimum price rule with the provided price value.
     */
    public static function getMinPriceRule(float $minPrice): LineItemUnitPriceRule
    {
        $rule = new LineItemUnitPriceRule();
        $rule->assign(['amount' => $minPrice, 'operator' => LineItemUnitPriceRule::OPERATOR_GTE]);

        return $rule;
    }

    /**
     * Gets a minimum quantity rule for the provided line item Id.
     */
    public static function getMinQuantityRule(string $itemID, int $quantity): LineItemWithQuantityRule
    {
        $rule = new LineItemWithQuantityRule();
        $rule->assign(['id' => $itemID, 'quantity' => $quantity, 'operator' => LineItemWithQuantityRule::OPERATOR_GTE]);

        return $rule;
    }

    /**
     * @param array<mixed> $itemIDs
     */
    public static function getProductsRule(array $itemIDs): LineItemRule
    {
        $rule = new LineItemRule();
        $rule->assign(['identifiers' => $itemIDs, 'operator' => LineItemRule::OPERATOR_EQ]);

        return $rule;
    }

    public static function getLineItemListPriceRule(float $price): LineItemListPriceRule
    {
        $rule = new LineItemListPriceRule();
        $rule->assign(['amount' => $price, 'operator' => LineItemListPriceRule::OPERATOR_GTE]);

        return $rule;
    }
}
