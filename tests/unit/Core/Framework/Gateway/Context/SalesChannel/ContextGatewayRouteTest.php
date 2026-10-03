<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopwell\Core\Framework\Gateway\Context\Extension\ContextGatewayRouteExtension;
use Shopwell\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayRoute::class)]
class ContextGatewayRouteTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $route = new ContextGatewayRoute(static::createStub(AppContextGateway::class), new ExtensionDispatcher(new EventDispatcher()));

        $this->expectException(DecorationPatternException::class);

        $route->getDecorated();
    }

    public function testLoad(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $request = new Request([], ['foo' => 'bar', 'bat' => 'baz']);

        $expectedPayload = new ContextGatewayPayloadStruct($cart, $context, new RequestDataBag(['foo' => 'bar', 'bat' => 'baz']));

        $appContextGateway = $this->createMock(AppContextGateway::class);
        $appContextGateway
            ->expects($this->once())
            ->method('process')
            ->with(static::equalTo($expectedPayload));

        $route = new ContextGatewayRoute($appContextGateway, new ExtensionDispatcher(new EventDispatcher()));
        $route->load($request, $cart, $context);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('context-gateway-route.load.pre', static function (ContextGatewayRouteExtension $extension) use ($request, $cart, $context, $response): void {
            static::assertSame(['request' => $request, 'cart' => $cart, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContextGatewayRoute(
            static::createStub(AppContextGateway::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $cart, $context));
    }
}
