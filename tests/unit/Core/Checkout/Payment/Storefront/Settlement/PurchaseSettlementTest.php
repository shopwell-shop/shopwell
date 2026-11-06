<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Storefront\Settlement;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlementResult;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseStore;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PurchaseSettlement::class)]
class PurchaseSettlementTest extends TestCase
{
    private const PURCHASE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NOW = '2026-06-01 12:00:00';

    private Connection&Stub $connection;

    private Context $context;

    private PurchaseSettlement $settlement;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(static fn (callable $callback) => $callback());
        $this->context = Context::createDefaultContext();
        $this->settlement = new PurchaseSettlement($this->connection, new MockClock(self::NOW));
    }

    public function testSettlesAPurchaseWhoseAmountTheChannelConfirmed(): void
    {
        $purchase = $this->purchase();
        $purchase->expects($this->once())
            ->method('settle')
            ->with('PAY-1', static::isInstanceOf(\DateTimeInterface::class), $this->context);

        static::assertSame(
            PurchaseSettlementResult::Settled,
            $this->settle($this->store($purchase))
        );
    }

    public function testRepeatedNotificationDoesNotSettleTwice(): void
    {
        $purchase = $this->purchase(isSettled: true);
        $purchase->expects($this->never())->method('settle');
        $purchase->expects($this->never())->method('fail');

        static::assertSame(PurchaseSettlementResult::AlreadySettled, $this->settle($this->store($purchase)));
    }

    public function testAmountTheCustomerDidNotAgreeToIsRecordedAsAFailure(): void
    {
        $purchase = $this->purchase();
        $purchase->expects($this->never())->method('settle');
        $purchase->expects($this->once())
            ->method('fail')
            ->with('PAY-1', static::isInstanceOf(\DateTimeInterface::class), $this->context);

        static::assertSame(PurchaseSettlementResult::AmountMismatch, $this->settle($this->store($purchase), 1));
    }

    public function testPurchaseThatCannotBeSettledAnyMoreFailsVisibly(): void
    {
        $purchase = $this->purchase(isSettleable: false);
        $purchase->expects($this->never())->method('settle');
        $purchase->expects($this->once())->method('fail');

        static::assertSame(PurchaseSettlementResult::NotSettleable, $this->settle($this->store($purchase)));
    }

    public function testUnknownPurchaseIsAnsweredWithoutWriting(): void
    {
        static::assertSame(PurchaseSettlementResult::NotFound, $this->settle($this->store(null)));
    }

    private function settle(PurchaseStore $store, int $paidMinor = 12800): PurchaseSettlementResult
    {
        return $this->settlement->settle($store, self::PURCHASE_ID, $paidMinor, 'PAY-1', $this->context);
    }

    private function store(?SettleablePurchase $purchase): PurchaseStore
    {
        $store = static::createStub(PurchaseStore::class);
        $store->method('lockForSettlement')->willReturn($purchase);

        return $store;
    }

    private function purchase(
        bool $isSettled = false,
        bool $isSettleable = true,
    ): SettleablePurchase&MockObject {
        $purchase = $this->createMock(SettleablePurchase::class);
        $purchase->method('purchaseId')->willReturn(self::PURCHASE_ID);
        $purchase->method('amountMinor')->willReturn(12800);
        $purchase->method('isSettled')->willReturn($isSettled);
        $purchase->method('isSettleable')->willReturn($isSettleable);

        return $purchase;
    }
}
