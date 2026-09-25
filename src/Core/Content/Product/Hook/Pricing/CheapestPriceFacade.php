<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Hook\Pricing;

use Shopwell\Core\Checkout\Cart\Facade\PriceFacade;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * The CheapestPriceFacade is a wrapper around the cheapest price of the product.
 *
 * @script-service product
 */
#[Package('checkout')]
class CheapestPriceFacade extends PriceFacade
{
    /**
     * @internal
     */
    public function __construct(
        protected Entity|LineItem $item,
        protected CalculatedPrice $price,
        protected ScriptPriceStubs $priceStubs,
        protected SalesChannelContext $context
    ) {
        if (!$this->price instanceof CalculatedCheapestPrice) {
            throw ProductException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }
        if (!$this->item instanceof Entity) {
            throw ProductException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }
    }

    /**
     * `reset()` allows to reset the cheapest price to the original price of the product.
     *
     * @example pricing-cases/product-pricing.twig 64 1 Reset the product price to default
     */
    public function reset(): void
    {
        $this->change(null);
    }

    /**
     * `change()` allows to overwrite the cheapest price of the current price scope. The provided price will be recalculated
     * over the quantity price calculator to consider quantity, tax rule and cash rounding configurations.
     *
     * @example pricing-cases/product-pricing.twig 60 5 Overwrite prices with a static defined collection
     * @example pricing-cases/product-pricing.twig 92 1 Overwrite the cheapest price with the original price
     * @example pricing-cases/product-pricing.twig 72 1 Discount the cheapest price by 10%
     *
     * @param PriceFacade|PriceCollection|CalculatedPrice|null $price You can provide different values to overwrite the cheapest price. In case of null, it uses the original single price of the product.
     * @param bool $range Allows to switch the `hasRange` attribute of the cheapest price
     */
    public function change(PriceFacade|PriceCollection|CalculatedPrice|null $price, bool $range = false): void
    {
        if (!$this->item instanceof Entity) {
            throw ProductException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        if (!$this->price instanceof CalculatedCheapestPrice) {
            throw ProductException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        if ($price === null) {
            /** @var CalculatedPrice $price */
            $price = $this->item->get('calculatedPrice');
        }

        if ($price instanceof PriceFacade) {
            $price = $price->getInner();
        }

        if ($price instanceof PriceCollection) {
            $value = $this->getPriceForTaxState($price, $this->context);

            $definition = new QuantityPriceDefinition(
                $value,
                $this->price->getTaxRules(),
                $this->getQuantity()
            );

            $price = $this->priceStubs->calculateQuantity($definition, $this->context);
        }

        if (!$price instanceof CalculatedPrice) {
            throw ProductException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        $this->price->overwrite(
            $price->getUnitPrice(),
            $price->getTotalPrice(),
            $price->getCalculatedTaxes(),
        );

        $this->price->setHasRange($range);
    }
}
