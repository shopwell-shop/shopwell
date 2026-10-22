<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
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
class CustomerSalutationRule extends Rule
{
    final public const RULE_NAME = 'customerSalutation';

    /**
     * @param list<string>|null $genderCodes
     *
     * @internal
     */
    public function __construct(
        public string $operator = self::OPERATOR_EQ,
        public ?array $genderCodes = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['genderCodes'] = RuleConstraints::stringArray();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }
        $gender = $customer->getGender();

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $gender === null;
        }

        if ($gender === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::stringArray($gender, $this->genderCodes ?? [], $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->stringField('genderCodes');
    }
}
