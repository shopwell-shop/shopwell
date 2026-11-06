<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\OrderReturn\SalesChannel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderReturnRouteContractTest extends TestCase
{
    use KernelTestBehaviour;

    private const ID = '11111111111111111111111111111111';

    #[DataProvider('actions')]
    public function testCustomerActionMatchesItsNamedAuthenticatedRoute(string $action): void
    {
        $match = $this->match('/store-api/order-return/actions/' . self::ID . '/' . $action, Request::METHOD_POST);

        static::assertSame('store-api.order-return.' . $action, $match['_route']);
        static::assertSame(self::ID, $match['id']);
        static::assertTrue($match[PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED]);
    }

    #[DataProvider('actions')]
    public function testPreviousCustomerActionHasNoCompatibilityAlias(string $action): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->match('/store-api/order-return/' . self::ID . '/' . $action, Request::METHOD_POST);
    }

    #[DataProvider('unchangedRoutes')]
    public function testPositionsAndGuestRoutesKeepTheirContracts(string $path, string $method, string $name): void
    {
        $match = $this->match($path, $method);

        static::assertSame($name, $match['_route']);
    }

    public function testGeneratedStoreApiSchemaDocumentsTheNewActionPaths(): void
    {
        $response = static::getContainer()->get(StoreApiInfoController::class)->info(new Request());
        $content = $response->getContent();
        static::assertNotFalse($content);
        /** @var array{paths: array<string, array<string, mixed>>} $schema */
        $schema = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        foreach (['cancel', 'ship', 'message'] as $action) {
            $path = '/order-return/actions/{id}/' . $action;
            static::assertArrayHasKey($path, $schema['paths']);
            static::assertArrayNotHasKey('/order-return/{id}/' . $action, $schema['paths']);
            static::assertSame('orderReturn' . ucfirst($action), $schema['paths'][$path]['post']['operationId']);
            static::assertArrayHasKey('200', $schema['paths'][$path]['post']['responses']);
        }

        static::assertArrayHasKey('/order-return/positions/{orderId}', $schema['paths']);
        static::assertArrayHasKey('/order-return/guest/{id}', $schema['paths']);
        static::assertArrayHasKey('/order-return/guest/{id}/cancel', $schema['paths']);
        static::assertArrayHasKey('/order-return/guest/{id}/ship', $schema['paths']);
        static::assertArrayHasKey('/order-return/guest/{id}/message', $schema['paths']);
    }

    public static function actions(): \Generator
    {
        yield 'cancel' => ['cancel'];
        yield 'ship' => ['ship'];
        yield 'message' => ['message'];
    }

    public static function unchangedRoutes(): \Generator
    {
        yield 'positions' => ['/store-api/order-return/positions/' . self::ID, Request::METHOD_GET, 'store-api.order-return.positions'];
        yield 'guest list' => ['/store-api/order-return/guest', Request::METHOD_GET, 'store-api.order-return.guest.load'];
        yield 'guest create' => ['/store-api/order-return/guest', Request::METHOD_POST, 'store-api.order-return.guest.create'];
        yield 'guest verify' => ['/store-api/order-return/guest/verify', Request::METHOD_POST, 'store-api.order-return.guest.verify'];
        yield 'guest detail' => ['/store-api/order-return/guest/' . self::ID, Request::METHOD_GET, 'store-api.order-return.guest.detail'];
        yield 'guest cancel' => ['/store-api/order-return/guest/' . self::ID . '/cancel', Request::METHOD_POST, 'store-api.order-return.guest.cancel'];
        yield 'guest ship' => ['/store-api/order-return/guest/' . self::ID . '/ship', Request::METHOD_POST, 'store-api.order-return.guest.ship'];
        yield 'guest message' => ['/store-api/order-return/guest/' . self::ID . '/message', Request::METHOD_POST, 'store-api.order-return.guest.message'];
    }

    /**
     * @return array<string, mixed>
     */
    private function match(string $path, string $method): array
    {
        $router = static::getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);

        $matcher = new UrlMatcher($router->getRouteCollection(), new RequestContext(method: $method));

        return $matcher->match($path);
    }
}
