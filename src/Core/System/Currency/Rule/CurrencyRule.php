<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\Currency\CurrencyDefinition;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class CurrencyRule extends Rule
{
    final public const RULE_NAME = 'currency';

    /**
     * @param list<string>|null $currencyIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $currencyIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return RuleComparison::uuids([$scope->getContext()->getCurrencyId()], $this->currencyIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'currencyIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('currencyIds', CurrencyDefinition::ENTITY_NAME, true);
    }
}
