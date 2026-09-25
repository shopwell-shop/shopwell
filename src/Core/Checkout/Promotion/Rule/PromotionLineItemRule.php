<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Cart\Rule\LineItemScope;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
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
class PromotionLineItemRule extends Rule
{
    final public const RULE_NAME = 'promotionLineItem';

    /**
     * @param list<string>|null $identifiers
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $identifiers = null
    ) {
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

        $promotionLineItems = $scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $hasNoPromotionLineItems = $promotionLineItems === [];

        if ($hasNoPromotionLineItems) {
            return $this->operator === self::OPERATOR_NEQ;
        }

        foreach ($promotionLineItems as $lineItem) {
            if ($this->lineItemMatches($lineItem)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>|null
     */
    public function getIdentifiers(): ?array
    {
        return $this->identifiers;
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
            ->entitySelectField('identifiers', PromotionDefinition::ENTITY_NAME, true);
    }

    private function lineItemMatches(LineItem $lineItem): bool
    {
        $promotionId = $lineItem->getPayloadValue('promotionId');
        if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE || $promotionId === null) {
            return $this->operator === self::OPERATOR_NEQ;
        }

        return RuleComparison::uuids([$promotionId], $this->identifiers, $this->operator);
    }
}
