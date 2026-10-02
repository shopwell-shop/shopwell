<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Category\CategoryDefinition;
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
class LineItemInCategoryRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemInCategory';

    /**
     * @param list<string> $categoryIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $categoryIds = []
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchesOneOfCategory($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $lineItem) {
            if (!\array_key_exists('categoryIds', $lineItem->getPayload())) {
                continue;
            }

            if ($this->matchesOneOfCategory($lineItem)) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['categoryIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true, true)
            ->entitySelectField('categoryIds', CategoryDefinition::ENTITY_NAME, true);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws CartException
     */
    private function matchesOneOfCategory(LineItem $lineItem): bool
    {
        return RuleComparison::uuids($lineItem->getPayloadValue('categoryIds'), $this->categoryIds, $this->operator);
    }
}
