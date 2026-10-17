<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.8.0 - Use \Shopwell\Core\Checkout\Cart\Rule\LineItemProductTypeRule instead.
 */
#[Package('fundamentals@after-sales')]
class LineItemProductStatesRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductStates';

    protected string $productState;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', LineItemProductTypeRule::class));

        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatches($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', LineItemProductTypeRule::class));

        return [
            'operator' => RuleConstraints::stringOperators(false),
            'productState' => RuleConstraints::choice([
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]),
        ];
    }

    public function getConfig(): ?RuleConfig
    {
        if (Feature::isActive('v6.8.0.0')) {
            return null;
        }

        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->selectField('productState', [
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $states = [];

        Feature::callSilentIfInactive('v6.8.0.0', static function () use (&$states, $lineItem): void {
            $states = $lineItem->getStates();
        });

        return RuleComparison::stringArray($this->productState, array_values($states), $this->operator);
    }
}
