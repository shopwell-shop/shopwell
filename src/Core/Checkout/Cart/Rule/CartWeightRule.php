<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CartWeightRule extends Rule
{
    final public const RULE_NAME = 'cartWeight';

    protected float $weight;

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        ?float $weight = null
    ) {
        parent::__construct();
        $this->weight = (float) $weight;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        return RuleComparison::numeric($this->calculateCartWeight($scope->getCart()), $this->weight, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'weight' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('weight', ['unit' => RuleConfig::UNIT_WEIGHT]);
    }

    private function calculateCartWeight(Cart $cart): float
    {
        $weight = 0.0;

        foreach ($cart->getLineItems()->filterGoodsFlat() as $lineItem) {
            $itemWeight = $lineItem->getDeliveryInformation()?->getWeight() ?? 0.0;

            $weight += $itemWeight * $lineItem->getQuantity();
        }

        return $weight;
    }
}
