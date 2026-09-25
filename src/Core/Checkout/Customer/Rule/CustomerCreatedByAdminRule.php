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
class CustomerCreatedByAdminRule extends Rule
{
    final public const RULE_NAME = 'customerCreatedByAdmin';

    /**
     * @internal
     */
    public function __construct(protected bool $shouldCustomerBeCreatedByAdmin = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getCustomer()) {
            return false;
        }

        return $this->shouldCustomerBeCreatedByAdmin === (bool) $customer->getCreatedById();
    }

    public function getConstraints(): array
    {
        return [
            'shouldCustomerBeCreatedByAdmin' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('shouldCustomerBeCreatedByAdmin');
    }
}
