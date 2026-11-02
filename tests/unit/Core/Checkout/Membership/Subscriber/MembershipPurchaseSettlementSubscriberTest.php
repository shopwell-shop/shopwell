<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchaseStore;
use Shopwell\Core\Checkout\Membership\Subscriber\MembershipPurchaseSettlementSubscriber;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlementResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The subscriber is the only path from a paid payment to an activated membership, so the marker on the
 * payment is what decides whether a membership reacts at all.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPurchaseSettlementSubscriber::class)]
class MembershipPurchaseSettlementSubscriberTest extends TestCase
{
    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ATTACH_PREFIX = 'membership:';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testItListensToThePaidEvent(): void
    {
        static::assertSame(
            [PaymentOrderPaidEvent::class => 'onPaymentOrderPaid'],
            MembershipPurchaseSettlementSubscriber::getSubscribedEvents()
        );
    }

    public function testAPaidMembershipIsSettled(): void
    {
        $settlement = $this->createMock(PurchaseSettlement::class);
        $settlement->expects($this->once())
            ->method('settle')
            ->with(
                static::isInstanceOf(MembershipOrderPurchaseStore::class),
                self::ORDER_ID,
                19900,
                'PAY-1',
                $this->context,
            )
            ->willReturn(PurchaseSettlementResult::Settled);

        $this->dispatch($settlement, self::ATTACH_PREFIX . self::ORDER_ID);
    }

    public function testAPaymentWithoutAMarkerIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), null);
    }

    public function testAPaymentOfAnotherIntentIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), 'wallet_top_up:' . self::ORDER_ID);
    }

    public function testAMarkerWithoutAnIdIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), self::ATTACH_PREFIX);
    }

    private function unexpectedSettlement(): PurchaseSettlement
    {
        $settlement = $this->createMock(PurchaseSettlement::class);
        $settlement->expects($this->never())->method('settle');

        return $settlement;
    }

    private function dispatch(PurchaseSettlement $settlement, ?string $attach): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new MembershipPurchaseSettlementSubscriber(
            $settlement,
            static::createStub(MembershipOrderPurchaseStore::class),
            new PurchaseAttach(self::ATTACH_PREFIX),
        ));

        $dispatcher->dispatch(new PaymentOrderPaidEvent($this->paidOrder($attach), $this->context));
    }

    private function paidOrder(?string $attach): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'f1b2c3d4e5f60718293a4b5c6d7e8f90';
        $order->paymentNo = 'PAY-1';
        $order->outTradeNo = self::ORDER_ID;
        $order->paymentAppId = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 19900;
        $order->paidAmount = 19900;
        $order->currency = 'CNY';
        $order->subject = '年度金卡';
        $order->attach = $attach;
        $order->stateId = 'state-paid';

        return $order;
    }
}
