<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Facade\DiscountFacade;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DiscountFacade::class)]
class DiscountFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $item = new LineItem('foo', 'foo', 'foo');
        $item->setLabel('foo');
        $facade = new DiscountFacade($item);

        static::assertSame('foo', $facade->getId());
        static::assertSame('foo', $facade->getLabel());
    }
}
