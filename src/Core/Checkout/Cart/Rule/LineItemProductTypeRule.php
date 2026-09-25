<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductTypeRegistry;
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
class LineItemProductTypeRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProductType';

    protected string $productType;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(private readonly ?ProductTypeRegistry $productTypeRegistry = null)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
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
        return [
            'operator' => RuleConstraints::stringOperators(false),
            'productType' => RuleConstraints::choice($this->productTypeRegistry?->getTypes() ?? [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
            ]),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->selectField('productType', $this->productTypeRegistry?->getTypes() ?? [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
            ]);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        if (!$lineItem->hasPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE)) {
            return false;
        }

        $resolvedType = $lineItem->getPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE);

        if (!\is_string($resolvedType)) {
            return false;
        }

        return RuleComparison::string($resolvedType, $this->productType, $this->operator);
    }
}
