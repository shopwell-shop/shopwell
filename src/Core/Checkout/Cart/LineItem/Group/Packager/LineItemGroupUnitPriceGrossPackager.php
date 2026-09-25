<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group\Packager;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class LineItemGroupUnitPriceGrossPackager implements LineItemGroupPackagerInterface
{
    public function getKey(): string
    {
        return 'PRICE_UNIT_GROSS';
    }

    /**
     * This packager adds all items to a bundle, until the sum of their item prices (gross)
     * reaches the provided minimum value for the package.
     *
     * @throws CartException
     */
    public function buildGroupPackage(float $minPackageValue, LineItemFlatCollection $sortedItems, SalesChannelContext $context): LineItemGroup
    {
        $result = new LineItemGroup();

        $currentPackageSum = 0.0;

        foreach ($sortedItems as $lineItem) {
            if ($lineItem->getPrice() === null) {
                continue;
            }

            // add as long as the minimum package value is not reached
            if ($currentPackageSum >= $minPackageValue) {
                break;
            }

            // add the item to our result
            // with the current quantity
            $result->addItem($lineItem->getId(), $lineItem->getQuantity());

            /** @var CalculatedPrice $price */
            $price = $lineItem->getPrice();

            $grossPrice = $price->getUnitPrice();

            $currentPackageSum += $lineItem->getQuantity() * $grossPrice;
        }

        // if we have less results than our max value
        // return an empty list, because that is not a valid group
        if ($currentPackageSum < $minPackageValue) {
            return new LineItemGroup();
        }

        return $result;
    }
}
