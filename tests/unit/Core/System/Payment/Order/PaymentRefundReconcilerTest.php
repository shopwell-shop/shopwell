<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\RefundQuery;
use Shopwell\Core\System\Payment\Gateway\Request\RefundResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentRefundReconciler;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundReconciler::class)]
class PaymentRefundReconcilerTest extends TestCase
{
    public function testAProblemRefundDoesNotBlockTheNextRefund(): void
    {
        $app = PaymentControllerFixture::app('app', 'secret');
        $order = PaymentControllerFixture::order($app->id);
        $order->paymentApp = $app;
        $broken = PaymentControllerFixture::refund($order->id);
        $refund = PaymentControllerFixture::refund($order->id);
        $refund->paymentOrder = $order;
        $refund->paymentAttempt = $order->activeAttempt;
        $order->activeAttempt = null;
        $refundRepository = new StaticEntityRepository([
            new PaymentRefundCollection([$broken, $refund]),
            new PaymentRefundCollection([$refund]),
        ]);
        $states = StaticEntityRepository::of(StateMachineStateCollection::class, [['open-id', 'processing-id']]);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (object $request): void {
            static::assertInstanceOf(RefundQuery::class, $request);
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult(PaymentRefundStates::STATE_PROCESSING));
        });
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $providers = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider(
            'alipay',
            [PaymentControllerFixture::channel('alipay_h5', 'alipay')],
            gatewayFactory: $factory,
        ));
        $config = PaymentControllerFixture::configResolver(
            $providers,
            PaymentControllerFixture::channelRegistry($providers),
            [PaymentControllerFixture::appAccount($app->id, credentials: ['app_id' => 'merchant'])],
        );
        $orderRepository = new StaticEntityRepository([new PaymentOrderCollection([$order])]);
        $service = PaymentControllerFixture::refundService(
            $refundRepository,
            $config,
            PaymentControllerFixture::orderService($orderRepository),
        );
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $reconciler = new PaymentRefundReconciler($refundRepository, $states, $service, PaymentControllerFixture::clock(), $logger);

        static::assertSame(1, $reconciler->reconcileDue(17));
        static::assertSame(17, $refundRepository->searchedCriteria[0]->getLimit());
        static::assertArrayHasKey('paymentAttempt', $refundRepository->searchedCriteria[0]->getAssociations());
        static::assertCount(2, $refundRepository->updates);
        static::assertArrayHasKey('reconciliationError', $refundRepository->updates[0][0]['responsePayload']);
        static::assertSame($refund->id, $refundRepository->updates[1][0]['id']);
    }
}
