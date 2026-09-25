<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Cart\Discount\Calculator;

use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorInterface;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Checkout\Promotion\PromotionException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DiscountAbsoluteCalculator implements DiscountCalculatorInterface
{
    public function __construct(private readonly AbsolutePriceCalculator $priceCalculator)
    {
    }

    /**
     * @throws PromotionException
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var AbsolutePriceDefinition $definition */
        $definition = $discount->getPriceDefinition();

        if (!$definition instanceof AbsolutePriceDefinition) {
            throw PromotionException::invalidPriceDefinition($discount->getLabel(), $discount->getCode());
        }

        $affectedPrices = $packages->getAffectedPrices();

        $totalOriginalSum = $affectedPrices->getTotalPriceAmount();
        $discountValue = -min(abs($definition->getPrice()), $totalOriginalSum);

        $price = $this->priceCalculator->calculate(
            $discountValue,
            $affectedPrices,
            $context
        );

        $composition = $this->getCompositionItems(
            $discountValue,
            $packages,
            $totalOriginalSum
        );

        return new DiscountCalculatorResult($price, $composition);
    }

    /**
     * @return list<DiscountCompositionItem>
     */
    private function getCompositionItems(float $discountValue, DiscountPackageCollection $packages, float $totalOriginalSum): array
    {
        $items = [];

        foreach ($packages as $package) {
            foreach ($package->getCartItems() as $lineItem) {
                if ($lineItem->getPrice() === null) {
                    continue;
                }

                $itemTotal = $lineItem->getPrice()->getTotalPrice();

                $factor = $totalOriginalSum === 0.0 ? 0 : $itemTotal / $totalOriginalSum;

                $items[] = new DiscountCompositionItem(
                    $lineItem->getId(),
                    $lineItem->getQuantity(),
                    abs($discountValue) * $factor
                );
            }
        }

        return $items;
    }
}
