<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletTopUpSettlementSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The subscriber is the only path from a paid payment to a credited wallet, so the marker on the
 * payment is what decides whether the wallet reacts at all.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpSettlementSubscriber::class)]
class WalletTopUpSettlementSubscriberTest extends TestCase
{
    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testItListensToThePaidEvent(): void
    {
        static::assertSame(
            [PaymentOrderPaidEvent::class => 'onPaymentOrderPaid'],
            WalletTopUpSettlementSubscriber::getSubscribedEvents()
        );
    }

    public function testAPaidTopUpIsCredited(): void
    {
        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->once())
            ->method('settle')
            ->with(self::TOP_UP_ID, 12800, 'PAY-1', $this->context);

        $this->dispatch($topUpService, WalletTopUpService::ATTACH_PREFIX . self::TOP_UP_ID);
    }

    public function testAPaymentWithoutAMarkerIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), null);
    }

    public function testAPaymentOfAnotherIntentIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), 'membership_order:' . self::TOP_UP_ID);
    }

    public function testAMarkerWithoutAnIdIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), WalletTopUpService::ATTACH_PREFIX);
    }

    private function unexpectedService(): WalletTopUpService&MockObject
    {
        $topUpService = $this->createMock(WalletTopUpService::class);
        $topUpService->expects($this->never())->method('settle');

        return $topUpService;
    }

    private function dispatch(WalletTopUpService $topUpService, ?string $attach): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new WalletTopUpSettlementSubscriber($topUpService));

        $dispatcher->dispatch(new PaymentOrderPaidEvent($this->paidOrder($attach), $this->context));
    }

    private function paidOrder(?string $attach): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'f1b2c3d4e5f60718293a4b5c6d7e8f90';
        $order->paymentNo = 'PAY-1';
        $order->outTradeNo = self::TOP_UP_ID;
        $order->paymentAppId = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 12800;
        $order->paidAmount = 12800;
        $order->currency = 'CNY';
        $order->subject = 'Wallet top-up';
        $order->attach = $attach;
        $order->stateId = 'state-paid';

        return $order;
    }
}
