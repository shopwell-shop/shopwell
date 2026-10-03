<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
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
class LineItemPropertyValueRule extends Rule
{
    public const RULE_NAME = 'cartLineItemPropertyValue';

    /**
     * @param list<string>|null $identifiers
     *
     * @internal
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $identifiers = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'identifiers' => RuleConstraints::uuids(),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof LineItemScope) {
            return $this->matchLineItem($scope->getLineItem());
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->filterGoodsFlat() as $item) {
            if (!\array_key_exists('propertyIds', $item->getPayload())) {
                continue;
            }

            if ($this->matchLineItem($item)) {
                return true;
            }
        }

        return false;
    }

    public function matchLineItem(LineItem $lineItem): bool
    {
        /**
         * @var list<string> $value
         */
        $value = $lineItem->getPayloadValue('propertyIds') ?? [];

        return RuleComparison::uuids(
            $value,
            $this->identifiers,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->entitySelectField('identifiers', PropertyGroupOptionDefinition::ENTITY_NAME, true);
    }
}
