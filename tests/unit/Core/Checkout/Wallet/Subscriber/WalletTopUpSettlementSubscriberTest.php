<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlementResult;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Event\WalletTopUpCreditedEvent;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseStore;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletTopUpSettlementSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The subscriber is the only path from a paid payment to a credited wallet, so the marker on the
 * payment is what decides whether the wallet reacts at all.
 *
 * It is also where a credited top-up gets announced, and only a top-up that was really credited now:
 * a repeated notification of the same payment must not tell the shop a second time that the money
 * arrived - the listener on the other side hands out points for it.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpSettlementSubscriber::class)]
class WalletTopUpSettlementSubscriberTest extends TestCase
{
    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CUSTOMER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private Context $context;

    private WalletTopUpPurchaseStore&Stub $purchases;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->purchases = static::createStub(WalletTopUpPurchaseStore::class);
    }

    public function testItListensToThePaidEvent(): void
    {
        static::assertSame(
            [PaymentOrderPaidEvent::class => 'onPaymentOrderPaid'],
            WalletTopUpSettlementSubscriber::getSubscribedEvents()
        );
    }

    public function testAPaidTopUpIsSettledAndAnnounced(): void
    {
        $settlement = $this->createMock(PurchaseSettlement::class);
        $settlement->expects($this->once())
            ->method('settle')
            ->with($this->purchases, self::TOP_UP_ID, 12800, 'PAY-1', $this->context)
            ->willReturn(PurchaseSettlementResult::Settled);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (object $event): bool {
                static::assertInstanceOf(WalletTopUpCreditedEvent::class, $event);
                static::assertSame(self::TOP_UP_ID, $event->getTopUpId());
                static::assertSame(self::CUSTOMER_ID, $event->getCustomerId());
                static::assertSame('CNY', $event->getCurrency());
                static::assertSame(128.0, $event->getAmount());

                return true;
            }))
            ->willReturnArgument(0);

        $this->dispatch($settlement, $dispatcher, 'wallet_top_up:' . self::TOP_UP_ID, [$this->topUp()]);
    }

    public function testARepeatedNotificationAnnouncesNothing(): void
    {
        $settlement = $this->createMock(PurchaseSettlement::class);
        $settlement->expects($this->once())
            ->method('settle')
            ->willReturn(PurchaseSettlementResult::AlreadySettled);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->dispatch($settlement, $dispatcher, 'wallet_top_up:' . self::TOP_UP_ID, [$this->topUp()]);
    }

    public function testAPaymentWithoutAMarkerIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), $this->unexpectedDispatcher(), null, []);
    }

    public function testAPaymentOfAnotherIntentIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), $this->unexpectedDispatcher(), 'membership_purchase:' . self::TOP_UP_ID, []);
    }

    public function testAMarkerWithoutAnIdIsIgnored(): void
    {
        $this->dispatch($this->unexpectedSettlement(), $this->unexpectedDispatcher(), 'wallet_top_up:', []);
    }

    private function unexpectedSettlement(): PurchaseSettlement&MockObject
    {
        $settlement = $this->createMock(PurchaseSettlement::class);
        $settlement->expects($this->never())->method('settle');

        return $settlement;
    }

    private function unexpectedDispatcher(): EventDispatcherInterface&MockObject
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        return $dispatcher;
    }

    /**
     * @param list<CustomerWalletTopUpEntity> $topUps
     */
    private function dispatch(
        PurchaseSettlement $settlement,
        EventDispatcherInterface $dispatcher,
        ?string $attach,
        array $topUps,
    ): void {
        $subscriber = new WalletTopUpSettlementSubscriber(
            $settlement,
            $this->purchases,
            new PurchaseAttach('wallet_top_up:'),
            StaticEntityRepository::of(CustomerWalletTopUpCollection::class, [$topUps]),
            $dispatcher,
        );

        $subscriber->onPaymentOrderPaid(new PaymentOrderPaidEvent($this->paidOrder($attach), $this->context));
    }

    private function topUp(): CustomerWalletTopUpEntity
    {
        $topUp = (new CustomerWalletTopUpEntity())->assign([
            'id' => self::TOP_UP_ID,
            'customerId' => self::CUSTOMER_ID,
            'currencyId' => 'd1b2c3d4e5f60718293a4b5c6d7e8f90',
            'currency' => 'CNY',
            'amount' => 128.0,
            'state' => CustomerWalletTopUpEntity::STATE_CREDITED,
        ]);

        $topUp->setUniqueIdentifier(self::TOP_UP_ID);

        return $topUp;
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
