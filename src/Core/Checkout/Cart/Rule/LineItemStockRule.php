<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.8.0 - reason:remove-rule - Use \Shopwell\Core\Checkout\Cart\Rule\LineItemActualStockRule instead.
 *
 * @final
 */
#[Package('fundamentals@after-sales')]
class LineItemStockRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemStock';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?int $stock = null
    ) {
        parent::__construct();
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-rule - Use \Shopwell\Core\Checkout\Cart\Rule\LineItemActualStockRule instead.
     */
    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchStock($scope->getLineItem());
        }

        if ($scope instanceof CartRuleScope) {
            return $this->matchStockFromCollection($scope->getCart()->getLineItems()->filterGoodsFlat());
        }

        return false;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-rule - Use \Shopwell\Core\Checkout\Cart\Rule\LineItemActualStockRule instead.
     */
    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::numericOperators(false),
            'stock' => RuleConstraints::int(),
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-rule - Use \Shopwell\Core\Checkout\Cart\Rule\LineItemActualStockRule instead.
     */
    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->intField('stock');
    }

    /**
     * @throws UnsupportedOperatorException|UnsupportedValueException|CartException
     */
    private function matchStock(LineItem $lineItem): bool
    {
        if ($this->stock === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new UnsupportedValueException(\gettype($this->stock), self::class);
            }
            throw CartException::unsupportedValue(\gettype($this->stock), self::class);
        }

        $deliveryInformation = $lineItem->getDeliveryInformation();

        if (!$deliveryInformation instanceof DeliveryInformation) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::numeric($deliveryInformation->getStock(), $this->stock, $this->operator);
    }

    /**
     * @param LineItem[] $lineItems
     */
    private function matchStockFromCollection(array $lineItems): bool
    {
        foreach ($lineItems as $lineItem) {
            if ($this->matchStock($lineItem)) {
                return true;
            }
        }

        return false;
    }
}
