<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopwell\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentStarter;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelResolver;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodCollectability;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentContextResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Routing\DefaultPaymentRoutingPolicy;
use Shopwell\Core\System\Payment\Routing\PaymentProductEligibility;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentGatewayHandler::class)]
class PaymentGatewayHandlerTest extends TestCase
{
    public function testSupportsRefundsButNoRecurringPayments(): void
    {
        $handler = $this->handler($this->orderTransactionRepository(), $this->reusedPaymentOrders());

        static::assertTrue($handler->supports(PaymentHandlerType::REFUND, 'payment-method-id', Context::createDefaultContext()));
        static::assertFalse($handler->supports(PaymentHandlerType::RECURRING, 'payment-method-id', Context::createDefaultContext()));
    }

    public function testPayRedirectsThePayerToTheUrlOfTheChannel(): void
    {
        $handler = $this->handler($this->orderTransactionRepository(), $this->reusedPaymentOrders());

        $response = $handler->pay($this->request(), $this->transaction(), Context::createDefaultContext(), null);

        static::assertSame('https://pay.example.test', $response->getTargetUrl());
    }

    public function testPayRedirectsToTheQrCodePage(): void
    {
        $paymentOrder = PaymentGatewayFixture::paymentOrder([
            'transactions' => [PaymentGatewayFixture::captureTransaction([
                'qr_code' => 'https://qr.example.test',
            ])],
        ]);

        static::assertNotNull($paymentOrder->activeAttempt);
        $paymentOrder->activeAttempt->handover = (new PaymentHandover(PaymentInteraction::QRCODE, extra: ['qr_code' => 'https://qr.example.test']))->jsonSerialize();
        $handler = $this->handler(
            $this->orderTransactionRepository(),
            $this->paymentOrders([$paymentOrder]),
            flow: PaymentInteraction::QRCODE
        );

        $response = $handler->pay($this->request(), $this->transaction(), Context::createDefaultContext(), null);

        static::assertSame('/generated/frontend.payment.gateway.qr/payment-order-id/PG-1', $response->getTargetUrl());
    }

    public function testPayRefusesAnSdkChannel(): void
    {
        $paymentOrderRepository = PaymentGatewayFixture::repository();

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->never())->method('create');
        $transactionRepository->expects($this->never())->method('update');

        $payResult = new PaymentStartResult(new PaymentHandover(PaymentInteraction::SDK, extra: ['order_str' => 'signed']));

        $handler = $this->handler(
            $this->orderTransactionRepository(),
            $paymentOrderRepository,
            transactionRepository: $transactionRepository,
            gateway: $this->gateway($payResult),
            flow: PaymentInteraction::SDK
        );

        $this->expectExceptionObject(PaymentProviderException::unknownMethod(PaymentGatewayFixture::PROVIDER));

        $handler->pay($this->request(), $this->transaction(), Context::createDefaultContext(), null);
    }

    /**
     * The app is part of the binding: without one there is no merchant account to collect on, and the
     * checkout says so instead of collecting into nowhere.
     */
    public function testPayRefusesABindingWithoutAnApp(): void
    {
        $handler = $this->handler($this->orderTransactionRepository(), $this->paymentOrders([]), withApp: false);

        $this->expectExceptionObject(CheckoutPaymentException::channelNotAvailableInStorefront(PaymentGatewayFixture::PROVIDER));

        $handler->pay($this->request(), $this->transaction(), Context::createDefaultContext(), null);
    }

    public function testFinalizeDoesNothingWhenTheCheckoutNeverOpenedAPayment(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $handler = $this->handler($this->orderTransactionRepository(), $this->paymentOrders([]), logger: $logger);

        $handler->finalize($this->request(), $this->transaction(), Context::createDefaultContext());
    }

    public function testFinalizeSwallowsAFailingReconciliation(): void
    {
        $paymentOrder = PaymentGatewayFixture::paymentOrder(['paymentApp' => PaymentGatewayFixture::platformAppEntity()]);

        $failingGateway = static::createStub(GatewayInterface::class);
        $failingGateway->method('execute')->willThrowException(new \RuntimeException('the channel timed out'));

        $configResolver = PaymentGatewayFixture::configResolver($failingGateway);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                static::anything(),
                static::callback(static function (array $context): bool {
                    static::assertSame('PG-1', $context['paymentNo']);
                    static::assertSame('order-transaction-id', $context['orderTransactionId']);

                    return true;
                })
            );

        $handler = $this->handler(
            $this->orderTransactionRepository(),
            $this->paymentOrders([$paymentOrder]),
            configResolver: $configResolver,
            logger: $logger
        );

        // no exception: the payment may well have succeeded, the scheduled reconciliation settles it
        $handler->finalize($this->request(), $this->transaction(), Context::createDefaultContext());
    }

    public function testRefundThrowsWhenTheTransactionHasNoPaymentOrder(): void
    {
        $handler = $this->handler($this->orderTransactionRepository(), $this->paymentOrders([]));

        $this->expectExceptionObject(PaymentOrderException::orderNotFound('order-transaction-id'));

        $handler->refund($this->refundTransaction(), Context::createDefaultContext());
    }

    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<PaymentOrderCollection> $paymentOrderRepository
     * @param EntityRepository<PaymentTransactionCollection>|null $transactionRepository
     * @param EntityRepository<OrderTransactionCaptureRefundCollection>|null $refundRepository
     */
    private function handler(
        EntityRepository $orderTransactionRepository,
        EntityRepository $paymentOrderRepository,
        ?EntityRepository $transactionRepository = null,
        ?EntityRepository $refundRepository = null,
        ?GatewayInterface $gateway = null,
        string $flow = PaymentInteraction::REDIRECT,
        ?PaymentConfigResolver $configResolver = null,
        ?LoggerInterface $logger = null,
        bool $withApp = true,
    ): PaymentGatewayHandler {
        $configResolver ??= PaymentGatewayFixture::configResolver($gateway, $flow);
        $orderService = PaymentGatewayFixture::paymentOrderService($paymentOrderRepository);

        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-id';
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = 'payment-method-id';
        $binding->paymentAppId = PaymentGatewayFixture::PLATFORM_APP_ID;
        $binding->methodTechnicalName = PaymentGatewayFixture::PROVIDER;
        $binding->routingPolicy = 'priority';

        if ($withApp) {
            $binding->paymentApp = PaymentGatewayFixture::platformAppEntity();
        }

        $channelRegistry = new PaymentProductRegistry(PaymentGatewayFixture::providerRegistry($flow));

        $starter = new OrderPaymentStarter($orderService, PaymentGatewayFixture::starterService(
            $transactionRepository ?? PaymentGatewayFixture::repository(),
            $orderService
        ), PaymentGatewayFixture::router());

        $refundRepository ??= PaymentGatewayFixture::repository();

        return new PaymentGatewayHandler(
            $orderTransactionRepository,
            new PaymentMethodChannelResolver(
                PaymentGatewayFixture::repository([$binding])
            ),
            $starter,
            $orderService,
            PaymentGatewayFixture::reconciler($paymentOrderRepository, $orderService, $configResolver),
            PaymentGatewayFixture::refundService(PaymentGatewayFixture::repository(), $orderService, $configResolver),
            $refundRepository,
            $logger ?? static::createStub(LoggerInterface::class),
            new PaymentMethodCollectability(
                PaymentGatewayFixture::repository([$binding]),
                new PaymentRouteResolver($channelRegistry, new PaymentProductEligibility(), $configResolver, [new DefaultPaymentRoutingPolicy()]),
            ),
            new StorefrontPaymentContextResolver(),
        );
    }

    /**
     * The order transaction with the order and the currency the handler reads for the amounts.
     *
     * @return EntityRepository<OrderTransactionCollection>
     */
    private function orderTransactionRepository(): EntityRepository
    {
        return PaymentGatewayFixture::repository([PaymentGatewayFixture::orderTransaction($this->order())]);
    }

    /**
     * A payment order that was already opened and whose channel answered a pay url.
     *
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function reusedPaymentOrders(): EntityRepository
    {
        $order = PaymentGatewayFixture::paymentOrder();
        static::assertNotNull($order->activeAttempt);
        $order->activeAttempt->handover = (new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.example.test'))->jsonSerialize();

        return $this->paymentOrders([$order]);
    }

    /**
     * @param list<PaymentOrderEntity> $orders
     *
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function paymentOrders(array $orders): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(PaymentGatewayFixture::searchResult($orders));

        return $repository;
    }

    private function gateway(PaymentStartResult $payResult): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($payResult): void {
            \assert($request instanceof Capture);
            $model = $request->getModel();
            \assert($model instanceof PaymentGatewayModel);
            $model->offsetSet(PaymentGatewayModel::RESULT, $payResult);
        });

        return $gateway;
    }

    private function order(): OrderEntity
    {
        return PaymentGatewayFixture::order();
    }

    private function transaction(): PaymentTransactionStruct
    {
        return new PaymentTransactionStruct('order-transaction-id', 'https://shop.example.test/return');
    }

    private function refundTransaction(): RefundPaymentTransactionStruct
    {
        return new RefundPaymentTransactionStruct('refund-id', 'order-transaction-id');
    }

    private function request(): Request
    {
        return new Request();
    }
}
