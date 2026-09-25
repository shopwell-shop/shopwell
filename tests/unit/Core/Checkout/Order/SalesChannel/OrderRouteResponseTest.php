<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRouteResponse::class)]
class OrderRouteResponseTest extends TestCase
{
    public function testPublicAPI(): void
    {
        $object = new EntitySearchResult(
            'order',
            0,
            new OrderCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $response = new OrderRouteResponse($object);
        $response->addPaymentChangeable(['foo' => true]);
        $response->addPaymentChangeable(['bar' => false]);

        static::assertEquals(
            new ArrayStruct(
                [
                    'orders' => $object,
                    'paymentChangeable' => ['foo' => true, 'bar' => false],
                ],
                'order-route-response-struct'
            ),
            $response->getObject()
        );

        static::assertEquals($object, $response->getOrders());
        static::assertEquals(['foo' => true, 'bar' => false], $response->getPaymentsChangeable());

        $response->setPaymentChangeable(['baz' => true]);

        static::assertEquals(
            new ArrayStruct(
                [
                    'orders' => $object,
                    'paymentChangeable' => ['baz' => true],
                ],
                'order-route-response-struct'
            ),
            $response->getObject()
        );
        static::assertEquals(['baz' => true], $response->getPaymentsChangeable());
    }
}
