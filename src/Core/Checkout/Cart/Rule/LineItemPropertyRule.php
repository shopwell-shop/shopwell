<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Property\PropertyGroupDefinition;
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
class LineItemPropertyRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemProperty';

    /**
     * @param list<string> $identifiers
     *
     * @internal
     */
    public function __construct(
        protected array $identifiers = [],
        protected string $operator = self::OPERATOR_EQ
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->lineItemMatch($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if ($this->lineItemMatch($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'identifiers' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('identifiers', PropertyGroupDefinition::ENTITY_NAME, true);
    }

    private function lineItemMatch(LineItem $lineItem): bool
    {
        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return false;
        }

        $properties = $lineItem->getPayloadValue('propertyIds') ?? [];
        $options = $lineItem->getPayloadValue('optionIds') ?? [];

        /** @var list<string> $ids */
        $ids = array_merge($properties, $options);

        return RuleComparison::uuids($ids, $this->identifiers, $this->operator);
    }
}
