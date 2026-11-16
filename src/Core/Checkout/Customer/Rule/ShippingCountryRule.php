<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Rule;

use Shopwell\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\Country\CountryDefinition;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class ShippingCountryRule extends Rule
{
    final public const RULE_NAME = 'customerShippingCountry';

    /**
     * @param list<string>|null $countryIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $countryIds = null
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $location = $scope instanceof DeliveryRuleScope
            ? $scope->getDelivery()->getLocation()
            : $scope->getSalesChannelContext()->getShippingLocation();

        $countryId = $scope->getSalesChannelContext()->getCustomer() === null
            ? $scope->getCustomer()?->getActiveShippingAddress()?->getCountryId()
            : null;

        $countryId ??= $location->getCountry()->getId();

        $parameter = [$countryId];
        if ($countryId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->countryIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['countryIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('countryIds', CountryDefinition::ENTITY_NAME, true);
    }
}
