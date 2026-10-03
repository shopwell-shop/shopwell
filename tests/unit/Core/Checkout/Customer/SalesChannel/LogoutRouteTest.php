<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\Extension\LogoutRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LogoutRoute::class)]
class LogoutRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('logout-route.logout.pre', static function (LogoutRouteExtension $extension) use ($context, $data, $response): void {
            static::assertSame(['context' => $context, 'data' => $data], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new LogoutRoute(
            static::createStub(SalesChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CartService::class),
            static::createStub(SalesChannelContextServiceInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->logout($context, $data));
    }
}
