<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\Extension\CustomerGroupRegistrationSettingsRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRouteResponse;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerGroupRegistrationSettingsRoute::class)]
class CustomerGroupRegistrationSettingsRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $customerGroupId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $response = new CustomerGroupRegistrationSettingsRouteResponse(new CustomerGroupEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('customer-group-registration-settings-route.load.pre', static function (CustomerGroupRegistrationSettingsRouteExtension $extension) use ($customerGroupId, $context, $response): void {
            static::assertSame(['customerGroupId' => $customerGroupId, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CustomerGroupRegistrationSettingsRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($customerGroupId, $context));
    }
}
