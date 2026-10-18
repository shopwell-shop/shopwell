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
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
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

        $handler = $this->handler(
            $this->orderTransactionRepository(),
            $this->paymentOrders([$paymentOrder]),
            flow: PaymentFlow::QRCODE
        );

        $response = $handler->pay($this->request(), $this->transaction(), Context::createDefaultContext(), null);

        static::assertSame('/generated/frontend.payment.gateway.qr/PG-1', $response->getTargetUrl());
    }

    public function testPayRefusesAnSdkChannel(): void
    {
        $created = PaymentGatewayFixture::paymentOrder();
        $paymentOrderRepository = $this->paymentOrdersForCreate($created);

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('create');
        $transactionRepository->expects($this->once())->method('update');

        $payResult = new PayResult(new PayParams(PaymentFlow::SDK, extra: ['order_str' => 'signed']));

        $handler = $this->handler(
            $this->orderTransactionRepository(),
            $paymentOrderRepository,
            transactionRepository: $transactionRepository,
            gateway: $this->gateway($payResult),
            flow: PaymentFlow::SDK
        );

        $this->expectExceptionObject(PaymentException::channelNotAvailableInStorefront(PaymentGatewayFixture::CHANNEL));

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

        $this->expectExceptionObject(PaymentException::orderNotFound('order-transaction-id'));

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
        PaymentFlow $flow = PaymentFlow::REDIRECT,
        ?PaymentConfigResolver $configResolver = null,
        ?LoggerInterface $logger = null,
    ): PaymentGatewayHandler {
        $configResolver ??= PaymentGatewayFixture::configResolver($gateway, $flow);
        $orderService = PaymentGatewayFixture::paymentOrderService($paymentOrderRepository, $configResolver);

        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-id';
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = 'payment-method-id';
        $binding->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $binding->channelTechnicalName = PaymentGatewayFixture::CHANNEL;

        $starter = new OrderPaymentStarter(
            PaymentGatewayFixture::platformApp(),
            PaymentGatewayFixture::providerRegistry($flow),
            $orderService,
            PaymentGatewayFixture::captureService(
                $transactionRepository ?? PaymentGatewayFixture::repository(),
                $configResolver,
                $orderService
            ),
            PaymentGatewayFixture::router()
        );

        $refundRepository ??= PaymentGatewayFixture::repository();

        return new PaymentGatewayHandler(
            $orderTransactionRepository,
            new PaymentMethodChannelResolver(
                PaymentGatewayFixture::repository([$binding]),
                new PaymentChannelRegistry(PaymentGatewayFixture::providerRegistry($flow))
            ),
            $starter,
            $orderService,
            PaymentGatewayFixture::reconciler($paymentOrderRepository, $orderService, $configResolver),
            PaymentGatewayFixture::refundService(PaymentGatewayFixture::repository(), $orderService, $configResolver),
            $refundRepository,
            $logger ?? static::createStub(LoggerInterface::class)
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
        return $this->paymentOrders([
            PaymentGatewayFixture::paymentOrder([
                'transactions' => [PaymentGatewayFixture::captureTransaction([
                    'http_method' => 'GET',
                    'answer' => 'https://pay.example.test',
                ])],
            ]),
        ]);
    }

    /**
     * @param list<PaymentOrderEntity> $orders
     *
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function paymentOrders(array $orders): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(PaymentGatewayFixture::searchResult($orders));

        return $repository;
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function paymentOrdersForCreate(PaymentOrderEntity $created): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(3))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($created): EntitySearchResult {
                if ($criteria->getIds() !== []) {
                    return PaymentGatewayFixture::searchResult([$created]);
                }

                return PaymentGatewayFixture::searchResult([]);
            });

        return $repository;
    }

    private function gateway(PayResult $payResult): GatewayInterface
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
