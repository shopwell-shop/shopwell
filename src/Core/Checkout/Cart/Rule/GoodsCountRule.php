<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\FilterRule;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class GoodsCountRule extends FilterRule
{
    final public const RULE_NAME = 'cartGoodsCount';

    protected int $count;

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        ?int $count = null
    ) {
        parent::__construct();
        $this->count = (int) $count;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope && !$scope instanceof LineItemScope) {
            return false;
        }

        $items = $scope instanceof CartRuleScope
            ? $scope->getCart()->getLineItems()->filterGoodsFlat()
            : ($scope->getLineItem()->isGood() ? [$scope->getLineItem()] : []);

        $goods = (new LineItemCollection($items))
            ->filter(fn (LineItem $li) => $this->filter?->match(new LineItemScope($li, $scope->getSalesChannelContext())) ?? true);

        return RuleComparison::numeric(
            (float) $goods->count(),
            (float) $this->count,
            $this->operator
        );
    }

    public function getConstraints(): array
    {
        return [
            'count' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }
}
