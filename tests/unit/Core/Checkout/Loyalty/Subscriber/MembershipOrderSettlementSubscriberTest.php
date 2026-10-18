<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
use Shopwell\Core\Checkout\Loyalty\Subscriber\MembershipOrderSettlementSubscriber;
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
#[CoversClass(MembershipOrderSettlementSubscriber::class)]
class MembershipOrderSettlementSubscriberTest extends TestCase
{
    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testItListensToThePaidEvent(): void
    {
        static::assertSame(
            [PaymentOrderPaidEvent::class => 'onPaymentOrderPaid'],
            MembershipOrderSettlementSubscriber::getSubscribedEvents()
        );
    }

    public function testAPaidMembershipIsActivated(): void
    {
        $membershipService = $this->createMock(MembershipService::class);
        $membershipService->expects($this->once())
            ->method('settle')
            ->with(self::ORDER_ID, 19900, 'PAY-1', $this->context);

        $this->dispatch($membershipService, MembershipService::ATTACH_PREFIX . self::ORDER_ID);
    }

    public function testAPaymentWithoutAMarkerIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), null);
    }

    public function testAPaymentOfAnotherIntentIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), 'wallet_top_up:' . self::ORDER_ID);
    }

    public function testAMarkerWithoutAnIdIsIgnored(): void
    {
        $this->dispatch($this->unexpectedService(), MembershipService::ATTACH_PREFIX);
    }

    private function unexpectedService(): MembershipService&MockObject
    {
        $membershipService = $this->createMock(MembershipService::class);
        $membershipService->expects($this->never())->method('settle');

        return $membershipService;
    }

    private function dispatch(MembershipService $membershipService, ?string $attach): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new MembershipOrderSettlementSubscriber($membershipService));

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
