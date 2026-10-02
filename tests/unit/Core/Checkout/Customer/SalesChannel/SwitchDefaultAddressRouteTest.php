<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\SwitchDefaultAddressRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\SwitchDefaultAddressRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SwitchDefaultAddressRoute::class)]
class SwitchDefaultAddressRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $type = SwitchDefaultAddressRoute::TYPE_SHIPPING;
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('switch-default-address-route.swap.pre', static function (SwitchDefaultAddressRouteExtension $extension) use ($addressId, $type, $context, $customer, $response): void {
            static::assertSame(['addressId' => $addressId, 'type' => $type, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SwitchDefaultAddressRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->swap($addressId, $type, $context, $customer));
    }
}
