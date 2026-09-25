<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Shipping\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodRouteResponse::class)]
class ShippingMethodRouteResponseTest extends TestCase
{
    public function testConstruct(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier('foo');

        $result = new EntitySearchResult(
            'shipping_method',
            1,
            $collection = new ShippingMethodCollection([$shippingMethod]),
            null,
            new Criteria(),
            Generator::generateSalesChannelContext()->getContext()
        );

        $response = new ShippingMethodRouteResponse($result);

        static::assertSame($collection, $response->getShippingMethods());
    }
}
