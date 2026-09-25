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
class CustomerBirthdayRule extends Rule
{
    final public const RULE_NAME = 'customerBirthday';

    /**
     * @internal
     *
     * @param string|array{from: string, to: string}|null $birthday
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected string|array|null $birthday = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::dateOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['birthday'] = RuleConstraints::date();

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $constraints['birthday'] = RuleConstraints::dateBetween();
        }

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

        $customerBirthday = $customer->getBirthday();

        if ($customerBirthday instanceof \DateTimeImmutable) {
            $customerBirthday = \DateTime::createFromImmutable($customerBirthday);
        }

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $customerBirthday === null;
        }

        if (!$customerBirthday instanceof \DateTime || $this->birthday === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::dateValue(
            $customerBirthday,
            $this->birthday,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE, true)
            ->dateField('birthday');
    }
}
