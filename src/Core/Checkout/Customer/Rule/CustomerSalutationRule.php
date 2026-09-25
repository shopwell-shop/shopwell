<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\Salutation\SalutationDefinition;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CustomerSalutationRule extends Rule
{
    final public const RULE_NAME = 'customerSalutation';

    /**
     * @param list<string>|null $salutationIds
     *
     * @internal
     */
    public function __construct(
        public string $operator = self::OPERATOR_EQ,
        public ?array $salutationIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['salutationIds'] = RuleConstraints::uuids();

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
        $salutation = $customer->getSalutation();

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $salutation === null;
        }

        if ($salutation === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::uuids([$salutation->getId()], $this->salutationIds, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('salutationIds', SalutationDefinition::ENTITY_NAME, true, ['labelProperty' => 'displayName']);
    }
}
