<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class LineItemDimensionWidthRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemDimensionWidth';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $amount = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchWidthDimension($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($lineItem->getDeliveryInformation() === null && !\array_key_exists(LineItem::PAYLOAD_PRODUCT_TYPE, $lineItem->getPayload())) {
                continue;
            }

            if ($this->matchWidthDimension($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['amount'] = RuleConstraints::float();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true, true)
            ->numberField('amount', ['unit' => RuleConfig::UNIT_DIMENSION]);
    }

    /**
     * @throws CartException
     * @throws UnsupportedOperatorException
     */
    private function matchWidthDimension(LineItem $lineItem): bool
    {
        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::numeric($deliveryInformation->getWidth(), $this->amount, $this->operator);
    }
}
