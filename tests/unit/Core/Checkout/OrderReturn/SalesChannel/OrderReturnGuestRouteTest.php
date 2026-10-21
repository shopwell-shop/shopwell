<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnGuestTokenService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest has no account, so the order number and the mail address are the only proof there is - and
 * nothing here may tell an anonymous visitor whether an order exists.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnGuestRoute::class)]
class OrderReturnGuestRouteTest extends TestCase
{
    private const TOKEN = 'the-token';

    public function testAGuestProvesTheOrderWithTheNumberAndTheMailAddress(): void
    {
        $service = static::createStub(OrderReturnService::class);
        $service->method('returnablePositions')->willReturn([['lineItemId' => 'line-item-id', 'quantity' => 1]]);
        $service->method('activeReasons')->willReturn([['id' => 'reason-id', 'label' => 'broken']]);

        $tokenService = $this->createMock(OrderReturnGuestTokenService::class);
        $tokenService->expects($this->once())
            ->method('issue')
            ->with('order-id', 'customer@example.com')
            ->willReturn('a-signed-token');

        $response = $this->route($service, $tokenService, orderRepository: $this->orderRepository())
            ->verify($this->verifyRequest('SW-10001', ' Customer@Example.com '), $this->context());

        static::assertSame('a-signed-token', $response->getObject()->get('token'));
        static::assertSame('SW-10001', $response->getObject()->get('orderNumber'));
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 1]], $response->getObject()->get('positions'));
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $response->getObject()->get('reasons'));
    }

    public function testAWrongMailAddressAnswersLikeAnUnknownOrder(): void
    {
        $this->expectExceptionObject(OrderReturnException::orderNotFound('SW-10001'));

        $this->route(orderRepository: $this->orderRepository())
            ->verify($this->verifyRequest('SW-10001', 'somebody-else@example.com'), $this->context());
    }

    public function testAnIncompleteProofAnswersLikeAnUnknownOrder(): void
    {
        $this->expectExceptionObject(OrderReturnException::orderNotFound(''));

        $this->route()->verify($this->verifyRequest('', ''), $this->context());
    }

    public function testAChannelThatDoesNotOfferGuestReturnsRefusesTheProof(): void
    {
        $settings = static::createStub(OrderReturnSettings::class);
        $settings->method('allowGuest')->willReturn(false);

        $this->expectExceptionObject(OrderReturnException::disabled());

        $this->route(settings: $settings, orderRepository: $this->orderRepository())
            ->verify($this->verifyRequest('SW-10001', 'customer@example.com'), $this->context());
    }

    public function testWithoutAValidTokenTheReturnsAreNotShown(): void
    {
        $this->expectExceptionObject(OrderReturnException::guestAccessDenied());

        $this->route(tokenService: $this->tokenService(resolved: null))
            ->load(new Request(query: ['token' => 'a-forged-token']), $this->context());
    }

    public function testTheTokenIsFoundInTheHeaderAsWell(): void
    {
        $tokenService = $this->createMock(OrderReturnGuestTokenService::class);
        $tokenService->expects($this->once())
            ->method('resolve')
            ->with(self::TOKEN)
            ->willReturn(['orderId' => 'order-id', 'email' => 'customer@example.com']);

        $this->route(
            $this->orderReturnService($this->orderReturn()),
            $tokenService,
            orderRepository: $this->orderRepository()
        )->detail(new Request(server: ['HTTP_X_ORDER_RETURN_TOKEN' => self::TOKEN]), 'return-id', $this->context());
    }

    public function testTheReturnsOfAnotherOrderAreNotShown(): void
    {
        $this->expectExceptionObject(OrderReturnException::guestAccessDenied());

        $this->route($this->orderReturnService($this->orderReturn('another-order-id')))
            ->detail(new Request(query: ['token' => self::TOKEN]), 'return-id', $this->context());
    }

    public function testAReturnOfTheProvenOrderIsShown(): void
    {
        $response = $this->route($this->orderReturnService($this->orderReturn()))
            ->detail(new Request(query: ['token' => self::TOKEN]), 'return-id', $this->context());

        static::assertSame('RT-1', $response->getObject()->get('orderReturn')['returnNumber']);
    }

    public function testACreatedReturnOfAGuestCarriesTheProofInsteadOfACustomer(): void
    {
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (array $payload): bool => $payload['orderId'] === 'order-id'
                    // an order id in the body of the request is ignored on purpose: the token names the order
                    && $payload['type'] === OrderReturnTypes::REFUND_ONLY
                    && $payload['positions'] === ['line-item-id' => 1]),
                static::isInstanceOf(Context::class),
                null
            )
            ->willReturn($this->orderReturn());

        $request = new Request(query: ['token' => self::TOKEN], request: [
            'orderId' => 'an-order-of-somebody-else',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 1]],
        ]);

        $response = $this->route($service)->create($request, $this->context());

        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testACreateWithoutAValidTokenIsRefused(): void
    {
        $this->expectExceptionObject(OrderReturnException::guestAccessDenied());

        $this->route(tokenService: $this->tokenService(resolved: null))
            ->create(new Request(query: ['token' => 'a-forged-token'], request: ['type' => OrderReturnTypes::REFUND_ONLY]), $this->context());
    }

    public function testAShipmentWithoutATrackingCodeIsRejected(): void
    {
        $this->expectExceptionObject(OrderReturnException::missingTrackingCode());

        $this->route($this->orderReturnService($this->orderReturn()))
            ->ship(new Request(query: ['token' => self::TOKEN]), 'return-id', $this->context());
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $this->expectExceptionObject(OrderReturnException::emptyMessage());

        $this->route($this->orderReturnService($this->orderReturn()))
            ->message(new Request(query: ['token' => self::TOKEN], request: ['content' => ' ']), 'return-id', $this->context());
    }

    public function testTheTokenHashIsWrittenToTheReturnInsteadOfTheToken(): void
    {
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('create')
            ->with(static::callback(static fn (array $payload): bool => $payload['guestEmail'] === 'customer@example.com'
                && $payload['guestTokenHash'] === 'the-hash-of-the-token'))
            ->willReturn($this->orderReturn());

        $tokenService = $this->createMock(OrderReturnGuestTokenService::class);
        $tokenService->method('resolve')->willReturn(['orderId' => 'order-id', 'email' => 'customer@example.com']);
        $tokenService->expects($this->once())->method('hash')->with(self::TOKEN)->willReturn('the-hash-of-the-token');

        $this->route($service, $tokenService)->create(
            new Request(query: ['token' => self::TOKEN], request: ['type' => OrderReturnTypes::RETURN_REFUND, 'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 1]]]),
            $this->context()
        );
    }

    /**
     * @param StaticEntityRepository<OrderReturnCollection>|null $orderReturnRepository
     * @param StaticEntityRepository<OrderCollection>|null $orderRepository
     */
    private function route(
        ?OrderReturnService $service = null,
        ?OrderReturnGuestTokenService $tokenService = null,
        ?OrderReturnSettings $settings = null,
        ?StaticEntityRepository $orderReturnRepository = null,
        ?StaticEntityRepository $orderRepository = null,
    ): OrderReturnGuestRoute {
        return new OrderReturnGuestRoute(
            $service ?? static::createStub(OrderReturnService::class),
            $tokenService ?? $this->tokenService(resolved: ['orderId' => 'order-id', 'email' => 'customer@example.com']),
            $settings ?? $this->settings(),
            $orderReturnRepository ?? new StaticEntityRepository([new OrderReturnCollection()]),
            $orderRepository ?? new StaticEntityRepository([new OrderCollection()]),
            new OrderReturnPresenter(),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
        );
    }

    /**
     * @param array{orderId: string, email: string}|null $resolved
     */
    private function tokenService(?array $resolved = ['orderId' => 'order-id', 'email' => 'customer@example.com']): OrderReturnGuestTokenService
    {
        $tokenService = static::createStub(OrderReturnGuestTokenService::class);
        $tokenService->method('resolve')->willReturn($resolved);
        $tokenService->method('hash')->willReturn('the-hash-of-the-token');

        return $tokenService;
    }

    private function settings(bool $allowGuest = true): OrderReturnSettings
    {
        $settings = static::createStub(OrderReturnSettings::class);
        $settings->method('allowGuest')->willReturn($allowGuest);

        return $settings;
    }

    private function orderReturnService(OrderReturnEntity $orderReturn): OrderReturnService
    {
        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);
        $service->method('cancel')->willReturn($orderReturn);
        $service->method('ship')->willReturn($orderReturn);
        $service->method('create')->willReturn($orderReturn);

        return $service;
    }

    private function orderReturn(string $orderId = 'order-id', string $guestEmail = 'customer@example.com'): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => $orderId,
            'guestEmail' => $guestEmail,
            'type' => OrderReturnTypes::REFUND_ONLY,
            'refundAmount' => 1999,
            'currency' => 'CNY',
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return $orderReturn;
    }

    /**
     * @return StaticEntityRepository<OrderCollection>
     */
    private function orderRepository(): StaticEntityRepository
    {
        $order = (new OrderEntity())->assign([
            'orderNumber' => 'SW-10001',
            'salesChannelId' => 'sales-channel-id',
            'orderCustomer' => (new OrderCustomerEntity())->assign([
                'id' => 'order-customer-id',
                'customerId' => null,
                'email' => 'customer@example.com',
            ]),
        ]);
        $order->setId('order-id');
        $order->setUniqueIdentifier('order-id');

        return new StaticEntityRepository([new OrderCollection([$order])]);
    }

    private function verifyRequest(string $orderNumber, string $email): Request
    {
        return new Request(request: ['orderNumber' => $orderNumber, 'email' => $email]);
    }

    private function context(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext();
    }
}
