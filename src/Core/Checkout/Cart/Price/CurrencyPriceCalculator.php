<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CurrencyPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        private readonly PriceSelector $priceSelector
    ) {
    }

    public function calculate(PriceCollection $price, CalculatedPriceCollection $prices, SalesChannelContext $context, int $quantity = 1): CalculatedPrice
    {
        $currency = $price->getCurrencyPrice($context->getCurrencyId());

        if (!$currency) {
            throw CartException::invalidPriceDefinition();
        }

        $taxRules = $this->percentageTaxRuleBuilder->buildCollectionRules($prices->getCalculatedTaxes(), $prices->getTotalPriceAmount());

        $selected = $this->priceSelector->select($currency, $taxRules, $context);
        $value = $selected->getValue();

        if ($currency->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getCurrency()->getFactor();
        }

        $definition = new QuantityPriceDefinition($value, $taxRules, $quantity);
        $definition->setIsCalculated($selected->isCalculated());

        return $this->priceCalculator->calculate($definition, $context);
    }
}
