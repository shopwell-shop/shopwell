<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\MeasurementSystem\Unit\ConvertedUnitSet;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Content\Property\PropertyGroupCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SalesChannelProductEntity extends ProductEntity
{
    protected PriceCollection $calculatedPrices;

    protected CalculatedPrice $calculatedPrice;

    protected ?PropertyGroupCollection $sortedProperties = null;

    protected CalculatedCheapestPrice $calculatedCheapestPrice;

    protected bool $isNew = false;

    protected int $calculatedMaxPurchase;

    protected ?CategoryEntity $seoCategory = null;

    /**
     * The container will be resolved on product.loaded event and
     * the detected cheapest price will be set for the current context rules
     */
    protected CheapestPrice|CheapestPriceContainer|null $cheapestPrice = null;

    protected ?CheapestPriceContainer $cheapestPriceContainer = null;

    protected ?ConvertedUnitSet $measurements = null;

    public function setCalculatedPrices(PriceCollection $prices): void
    {
        $this->calculatedPrices = $prices;
    }

    public function getCalculatedPrices(): PriceCollection
    {
        return $this->calculatedPrices;
    }

    public function getCalculatedPrice(): CalculatedPrice
    {
        return $this->calculatedPrice;
    }

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void
    {
        $this->calculatedPrice = $calculatedPrice;
    }

    public function getSortedProperties(): ?PropertyGroupCollection
    {
        return $this->sortedProperties;
    }

    public function setSortedProperties(?PropertyGroupCollection $sortedProperties): void
    {
        $this->sortedProperties = $sortedProperties;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }

    public function getCalculatedMaxPurchase(): int
    {
        return $this->calculatedMaxPurchase;
    }

    public function setCalculatedMaxPurchase(int $calculatedMaxPurchase): void
    {
        $this->calculatedMaxPurchase = $calculatedMaxPurchase;
    }

    public function getSeoCategory(): ?CategoryEntity
    {
        return $this->seoCategory;
    }

    public function setSeoCategory(?CategoryEntity $category): void
    {
        $this->seoCategory = $category;
    }

    public function getCalculatedCheapestPrice(): CalculatedCheapestPrice
    {
        return $this->calculatedCheapestPrice;
    }

    public function setCalculatedCheapestPrice(CalculatedCheapestPrice $calculatedCheapestPrice): void
    {
        $this->calculatedCheapestPrice = $calculatedCheapestPrice;
    }

    public function getCheapestPrice(): CheapestPrice|CheapestPriceContainer|null
    {
        return $this->cheapestPrice;
    }

    public function setCheapestPrice(?CheapestPrice $cheapestPrice): void
    {
        $this->cheapestPrice = $cheapestPrice;
    }

    public function setCheapestPriceContainer(CheapestPriceContainer $container): void
    {
        $this->cheapestPriceContainer = $container;
    }

    public function getCheapestPriceContainer(): ?CheapestPriceContainer
    {
        return $this->cheapestPriceContainer;
    }

    public function getMeasurements(): ?ConvertedUnitSet
    {
        return $this->measurements;
    }

    public function setMeasurements(ConvertedUnitSet $measurements): void
    {
        $this->measurements = $measurements;
    }
}
