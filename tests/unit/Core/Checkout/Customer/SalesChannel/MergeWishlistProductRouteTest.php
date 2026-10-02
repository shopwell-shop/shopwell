<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\MergeWishlistProductRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\MergeWishlistProductRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
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
#[CoversClass(MergeWishlistProductRoute::class)]
class MergeWishlistProductRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('merge-wishlist-product-route.merge.pre', static function (MergeWishlistProductRouteExtension $extension) use ($data, $context, $customer, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new MergeWishlistProductRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SalesChannelRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Connection::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->merge($data, $context, $customer));
    }
}
