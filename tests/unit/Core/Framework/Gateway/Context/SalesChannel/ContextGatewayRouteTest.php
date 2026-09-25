<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopwell\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopwell\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Test\Generator;
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
        $route = new ContextGatewayRoute(static::createStub(AppContextGateway::class));

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

        $route = new ContextGatewayRoute($appContextGateway);
        $route->load($request, $cart, $context);
    }
}
