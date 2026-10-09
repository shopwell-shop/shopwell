<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class NetPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
        private readonly CashRounding $priceRounding
    ) {
    }

    public function calculate(QuantityPriceDefinition $definition, CashRoundingConfig $config): CalculatedPrice
    {
        $unitPrice = $this->round($definition->getPrice(), $config);

        $taxRules = $definition->getTaxRules();

        $calculatedTaxes = $this->taxCalculator->calculateNetTaxes(
            $unitPrice,
            $definition->getTaxRules()
        );

        foreach ($calculatedTaxes as $tax) {
            $total = $this->priceRounding->mathRound($tax->getTax() * $definition->getQuantity(), $config);
            $taxPrice = $this->priceRounding->mathRound($tax->getPrice() * $definition->getQuantity(), $config);

            $tax->setTax($total);
            $tax->setPrice($taxPrice);
        }

        $price = $this->round(
            $unitPrice * $definition->getQuantity(),
            $config
        );

        $reference = $this->calculateReferencePrice($unitPrice, $definition->getReferencePriceDefinition(), $config);

        return new CalculatedPrice(
            $unitPrice,
            $price,
            $calculatedTaxes,
            $taxRules,
            $definition->getQuantity(),
            $reference,
            $this->calculateListPrice($unitPrice, $definition, $config),
            $this->calculateRegulationPrice($unitPrice, $definition, $config)
        );
    }

    private function calculateListPrice(float $unitPrice, QuantityPriceDefinition $definition, CashRoundingConfig $config): ?ListPrice
    {
        $listPrice = $definition->getListPrice();
        if (!$listPrice) {
            return null;
        }

        $listPrice = $this->round($listPrice, $config);

        if ($listPrice <= 0) {
            return null;
        }

        return ListPrice::createFromUnitPrice($unitPrice, $listPrice);
    }

    private function calculateRegulationPrice(float $unitPrice, QuantityPriceDefinition $definition, CashRoundingConfig $config): ?RegulationPrice
    {
        $regulationPrice = $definition->getRegulationPrice();
        if (!$regulationPrice) {
            return null;
        }

        $regulationPrice = $this->round($regulationPrice, $config);

        if ($regulationPrice <= 0) {
            return null;
        }

        return RegulationPrice::createFromUnitPrice($unitPrice, $regulationPrice);
    }

    private function calculateReferencePrice(float $price, ?ReferencePriceDefinition $definition, CashRoundingConfig $config): ?ReferencePrice
    {
        if (!$definition) {
            return null;
        }

        if ($definition->getPurchaseUnit() <= 0 || $definition->getReferenceUnit() <= 0) {
            return null;
        }

        $price = $price / $definition->getPurchaseUnit() * $definition->getReferenceUnit();

        $price = $this->priceRounding->mathRound($price, $config);

        return new ReferencePrice(
            $price,
            $definition->getPurchaseUnit(),
            $definition->getReferenceUnit(),
            $definition->getUnitName()
        );
    }

    private function round(float $price, CashRoundingConfig $config): float
    {
        if ($config->roundForNet()) {
            return $this->priceRounding->cashRound($price, $config);
        }

        return $this->priceRounding->mathRound($price, $config);
    }
}
