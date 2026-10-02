<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\AddWishlistProductRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddWishlistProductRoute::class)]
class AddWishlistProductRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('add-wishlist-product-route.add.pre', static function (AddWishlistProductRouteExtension $extension) use ($productId, $context, $customer, $response): void {
            static::assertSame(['productId' => $productId, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new AddWishlistProductRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SalesChannelRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->add($productId, $context, $customer));
    }
}
