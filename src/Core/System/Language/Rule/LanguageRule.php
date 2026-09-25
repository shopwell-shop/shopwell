<?php declare(strict_types=1);

namespace Shopwell\Core\System\Language\Rule;

use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\LanguageException;

/**
 * @final
 */
#[Package('fundamentals@discovery')]
class LanguageRule extends Rule
{
    final public const RULE_NAME = 'language';

    /**
     * @internal
     *
     * @param list<string>|null $languageIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $languageIds = null
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException|UnsupportedValueException|CustomerException
     */
    public function match(RuleScope $scope): bool
    {
        if ($this->languageIds === null) {
            throw LanguageException::unsupportedValue(\gettype($this->languageIds), self::class);
        }

        return RuleComparison::uuids([$scope->getContext()->getLanguageId()], $this->languageIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'languageIds' => RuleConstraints::uuids(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('languageIds', LanguageDefinition::ENTITY_NAME, true);
    }
}
