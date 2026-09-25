<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Symfony\Component\Clock\Clock;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CustomerAgeRule extends Rule
{
    final public const RULE_NAME = 'customerAge';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $age = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$this->age && $this->operator !== self::OPERATOR_EMPTY) {
            throw CustomerException::unsupportedValue(\gettype($this->age), self::class);
        }

        if (!$birthday = $customer->getBirthday()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $birthday = new \DateTime('@' . $birthday->getTimestamp());
        $now = Clock::get()->now();

        $age = $now->diff($birthday)->y;

        return RuleComparison::numeric($age, $this->age, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['age'] = RuleConstraints::float();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->intField('age', ['unit' => RuleConfig::UNIT_AGE]);
    }
}
