<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemFlatCollection::class)]
class LineItemFlatCollectionTest extends TestCase
{
    /**
     * This test verifies that its possible
     * to add a line item with a specific id multiple times.
     * It must not be aggregated within an associative array in the flat list.
     *
     * @throws CartException
     */
    public function testCanAddSameItemMultipleTimes(): void
    {
        $lineItem = new LineItem('ABC', '');
        $lineItem->setStackable(true);

        $collection = new LineItemFlatCollection();

        $collection->add($lineItem);
        $collection->add($lineItem);

        static::assertCount(2, $collection);
    }
}
