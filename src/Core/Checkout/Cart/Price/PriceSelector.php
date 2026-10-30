<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Price;

use Shopwell\Core\Checkout\Cart\Extension\PriceSelectorExtension;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Checkout\Cart\Tax\TaxRuleShares;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
final class PriceSelector
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @param TaxRuleCollection $taxRules the tax rules that apply to the price, used to derive a value the stored price does not provide
     */
    public function select(Price $price, TaxRuleCollection $taxRules, SalesChannelContext $context): SelectedPrice
    {
        return $this->extensions->publish(
            name: PriceSelectorExtension::NAME,
            extension: new PriceSelectorExtension($price, $taxRules, $context),
            function: $this->_select(...),
        );
    }

    private function _select(Price $price, TaxRuleCollection $taxRules, SalesChannelContext $context): SelectedPrice
    {
        $taxState = $context->getTaxState();

        if (!TaxRuleFingerprint::derivesPricesFromTaxRates($context)) {
            return new SelectedPrice(
                $taxState === CartPrice::TAX_STATE_GROSS ? $price->getGross() : $price->getNet(),
                isCalculated: true
            );
        }

        if ($taxState === CartPrice::TAX_STATE_GROSS) {
            return new SelectedPrice($price->getNet(), isCalculated: false);
        }

        $containedTax = $this->taxCalculator
            ->calculateGrossTaxes($price->getGross(), TaxRuleShares::ofGross($taxRules))
            ->getAmount();

        return new SelectedPrice($price->getGross() - $containedTax, isCalculated: true);
    }
}
