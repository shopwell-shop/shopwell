<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class IsActiveRule extends Rule
{
    final public const RULE_NAME = 'customerIsActive';

    /**
     * @internal
     */
    public function __construct(protected bool $isActive = false)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $customer = $scope->getCustomer();
        if (!$customer) {
            return false;
        }

        return $this->isActive === $customer->getActive();
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isActive');
    }

    public function getConstraints(): array
    {
        return [
            'isActive' => RuleConstraints::bool(true),
        ];
    }
}
