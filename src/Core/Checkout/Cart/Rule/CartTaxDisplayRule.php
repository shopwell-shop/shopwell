<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CartTaxDisplayRule extends Rule
{
    final public const RULE_NAME = 'cartTaxDisplay';

    /**
     * @internal
     */
    public function __construct(protected string $taxDisplay = CartPrice::TAX_STATE_GROSS)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return $this->taxDisplay === $scope->getSalesChannelContext()->getTaxState();
    }

    public function getConstraints(): array
    {
        return [
            'taxDisplay' => RuleConstraints::string(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->selectField('taxDisplay', [CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET]);
    }
}
