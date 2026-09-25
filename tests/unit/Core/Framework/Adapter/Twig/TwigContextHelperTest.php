<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigContextHelper::class)]
class TwigContextHelperTest extends TestCase
{
    public function testGetContextReturnsInjectedCoreContext(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();

        static::assertSame($context, TwigContextHelper::getContext([
            'context' => $context,
            'salesChannelContext' => $salesChannelContext,
        ]));
    }

    public function testGetContextReturnsCoreContextFromSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        static::assertSame($salesChannelContext->getContext(), TwigContextHelper::getContext([
            'context' => $salesChannelContext,
        ]));
    }

    public function testGetContextReturnsCoreContextFromSalesChannelContextFallback(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        static::assertSame($salesChannelContext->getContext(), TwigContextHelper::getContext([
            'salesChannelContext' => $salesChannelContext,
        ]));
    }

    public function testGetSalesChannelContextReturnsContextVariable(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        static::assertSame($salesChannelContext, TwigContextHelper::getSalesChannelContext([
            'context' => $salesChannelContext,
        ]));
    }

    public function testGetSalesChannelContextReturnsFallbackVariable(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        static::assertSame($salesChannelContext, TwigContextHelper::getSalesChannelContext([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ]));
    }

    public function testReturnsNullWithoutContext(): void
    {
        static::assertNull(TwigContextHelper::getContext([]));
        static::assertNull(TwigContextHelper::getSalesChannelContext([]));
    }
}
