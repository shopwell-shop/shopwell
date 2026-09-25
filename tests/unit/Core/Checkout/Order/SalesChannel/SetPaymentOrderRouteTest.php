<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\RuleLoaderResult;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetPaymentOrderRoute::class)]
class SetPaymentOrderRouteTest extends TestCase
{
    #[DataProvider('requestDataProvider')]
    public function testInvalidRequest(Request $request): void
    {
        $this->expectExceptionObject(OrderException::invalidUuid(''));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class)
        );

        $paymentOrderRoute->setPayment($request, static::createStub(SalesChannelContext::class));
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class)
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testInvalidPaymentMethod(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $paymentMethodId = Uuid::randomHex();
        $request = self::getRequest(['paymentMethodId' => $paymentMethodId, 'orderId' => Uuid::randomHex()]);

        $this->expectExceptionObject(OrderException::paymentMethodNotAvailable($paymentMethodId));

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testPaymentNotChangeable(): void
    {
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testPaymentMethodNotAfterOrderEnabled(): void
    {
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(false);
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        // afterOrderEnabled is enforced before the transaction-state check, so it must not be consulted.
        $orderService
            ->expects($this->never())
            ->method('isPaymentChangeableByTransactionState');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testReopenAndCancelTransactions(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);

        $transactionState = new OrderTransactionEntity();
        $transactionState->setId(Uuid::randomHex());
        $transactionState->setPaymentMethodId(Uuid::randomHex());
        $transactionState->setStateId(Uuid::randomHex());
        $transactionState->setAmount(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $transactionStateLastId = Uuid::randomHex();
        $transactionStateLast = new OrderTransactionEntity();
        $transactionStateLast->setId($transactionStateLastId);
        $transactionStateLast->setPaymentMethodId($paymentMethod->getId());
        $transactionStateLast->setStateId(Uuid::randomHex());
        $transactionStateLast->setAmount(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrimaryOrderTransactionId($transactionStateLastId);
        $order->setPrimaryOrderTransaction($transactionStateLast);
        $order->setTransactions(new OrderTransactionCollection([$transactionState, $transactionStateLast]));
        $order->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE));

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);
        $orderService
            ->expects($this->exactly(2))
            ->method('orderTransactionStateTransition');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($context);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            $orderConverter,
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    public function testSetPaymentMethod(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);

        $price = new CartPrice(
            100,
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice($price);

        $orderLater = new OrderEntity();
        $orderLater->setId(Uuid::randomHex());

        new EntitySearchResult(
            'order',
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'order',
                    1,
                    new OrderCollection([$order]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
                new EntitySearchResult(
                    'order',
                    1,
                    new OrderCollection([$orderLater]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                )
            );

        $orderRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function ($payload) use ($orderLater): EntityWrittenContainerEvent {
                static::assertCount(1, $payload);
                static::assertCount(1, $payload[0]['transactions']);

                $transactionState = new OrderTransactionEntity();
                $transactionState->setId($payload[0]['transactions'][0]['id']);

                $orderLater->setTransactions(new OrderTransactionCollection([$transactionState]));

                return new EntityWrittenContainerEvent(
                    Context::createDefaultContext(),
                    new NestedEventCollection(),
                    []
                );
            });

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request): bool => $request->attributes->getAlnum('orderId') === $order->getId()),
                static::callback(static fn (Cart $cart): bool => $cart->getToken() === $context->getToken()),
                $context
            )
            ->willReturn($response);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($context);
        $orderConverter
            ->expects($this->exactly(2))
            ->method('convertToCart')
            ->willReturnOnConsecutiveCalls(
                new Cart('converted-order-token'),
                new Cart('converted-order-token')
            );

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with(static::callback(static fn (Cart $cart): bool => $cart->getToken() === $context->getToken()));

        $cartRuleLoader = static::createStub(CartRuleLoader::class);
        $cartRuleLoader
            ->method('loadByCart')
            ->willReturnCallback(static fn (SalesChannelContext $context, Cart $cart): RuleLoaderResult => new RuleLoaderResult($cart, new RuleCollection()));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $orderRepository,
            $orderConverter,
            $cartRuleLoader,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    /**
     * @return iterable<string, Request[]>
     */
    public static function requestDataProvider(): iterable
    {
        yield 'request without payment method or order ids' => [
            self::getRequest([]),
        ];
        yield 'request with malformed payment method id' => [
            self::getRequest(['paymentMethodId' => 'some payment method id']),
        ];
        yield 'request with valid payment method id and malformed order id' => [
            self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => 'some order id']),
        ];
    }

    /**
     * @param array<string, true|string> $attributes
     */
    private static function getRequest(array $attributes): Request
    {
        $request = Request::create($_SERVER['APP_URL'], Request::METHOD_GET);

        foreach ($attributes as $key => $attribute) {
            $request->request->set($key, $attribute);
        }

        return $request;
    }
}
