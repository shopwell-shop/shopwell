<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price;

use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('checkout')]
class AmountCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CashRounding $rounding,
        private readonly PercentageTaxRuleBuilder $taxRuleBuilder,
        private readonly TaxCalculator $taxCalculator
    ) {
    }

    public function calculate(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
            return $this->calculateNetDeliveryAmount($prices, $shippingCosts);
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $this->calculateGrossAmount($prices, $shippingCosts, $context);
        }

        return $this->calculateNetAmount($prices, $shippingCosts, $context);
    }

    public function calculateTaxes(PriceCollection $prices, string $calculationType, string $taxState, CashRoundingConfig $itemRounding): CalculatedTaxCollection
    {
        if ($calculationType === SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL) {
            $taxes = $prices->getCalculatedTaxes();

            $taxes->round($this->rounding, $itemRounding);

            return $taxes;
        }

        $totalAmount = $prices->getTotalPriceAmount();
        $rules = $this->taxRuleBuilder->buildCollectionRules($prices->getCalculatedTaxes(), $totalAmount);

        if ($taxState === CartPrice::TAX_STATE_GROSS) {
            $taxes = $this->taxCalculator->calculateGrossTaxes($totalAmount, $rules);
        } else {
            $taxes = $this->taxCalculator->calculateNetTaxes($totalAmount, $rules);
        }

        $taxes->round($this->rounding, $itemRounding);

        return $taxes;
    }

    /**
     * Calculates the amount for a new delivery.
     * `CalculatedPrice::price` and `CalculatedPrice::netPrice` are equals and taxes are empty.
     */
    private function calculateNetDeliveryAmount(PriceCollection $prices, PriceCollection $shippingCosts): CartPrice
    {
        $totalPrice = $prices->getTotalPriceAmount();
        $total = $totalPrice + $shippingCosts->getTotalPriceAmount();

        return new CartPrice(
            $total,
            $total,
            $totalPrice,
            new CalculatedTaxCollection([]),
            new TaxRuleCollection([]),
            CartPrice::TAX_STATE_FREE
        );
    }

    /**
     * Calculates the amount for a gross delivery.
     * `CalculatedPrice::netPrice` contains the summed gross prices minus amount of calculated taxes.
     * `CalculatedPrice::price` contains the summed gross prices
     * Calculated taxes are based on the gross prices
     */
    private function calculateGrossAmount(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);
        $totalPrice = $all->getTotalPriceAmount();
        $taxes = $this->calculateTaxes($all, $context->getTaxCalculationType(), $context->getTaxState(), $context->getItemRounding());

        $price = $this->rounding->cashRound(
            $totalPrice,
            $context->getTotalRounding()
        );

        $net = $this->rounding->mathRound(
            $totalPrice - $taxes->getAmount(),
            $context->getItemRounding()
        );

        return new CartPrice(
            $net,
            $price,
            $prices->getTotalPriceAmount(),
            $taxes,
            $all->getTaxRules(),
            CartPrice::TAX_STATE_GROSS,
            $totalPrice
        );
    }

    /**
     * Calculates the amount for a net based delivery, but gross prices has be be payed
     * `CalculatedPrice::netPrice` contains the summed net prices.
     * `CalculatedPrice::price` contains the summed net prices plus amount of calculated taxes
     * Calculated taxes are based on the net prices
     */
    private function calculateNetAmount(PriceCollection $prices, PriceCollection $shippingCosts, SalesChannelContext $context): CartPrice
    {
        $all = $prices->merge($shippingCosts);
        $allTotalAmount = $all->getTotalPriceAmount();
        $taxes = $this->calculateTaxes($all, $context->getTaxCalculationType(), $context->getTaxState(), $context->getItemRounding());

        $price = $this->rounding->cashRound(
            $allTotalAmount + $taxes->getAmount(),
            $context->getTotalRounding()
        );

        return new CartPrice(
            $allTotalAmount,
            $price,
            $prices->getTotalPriceAmount(),
            $taxes,
            $all->getTaxRules(),
            CartPrice::TAX_STATE_NET,
            $allTotalAmount + $taxes->getAmount()
        );
    }
}
