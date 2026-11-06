<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Purchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchase;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseStore;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpPurchaseStore::class)]
class WalletTopUpPurchaseStoreTest extends TestCase
{
    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testLockedTopUpBecomesASettleablePurchase(): void
    {
        $rows = $this->createMock(RowLock::class);
        $rows->expects($this->once())
            ->method('lock')
            ->with('customer_wallet_top_up', ['id' => self::TOP_UP_ID])
            ->willReturn(['id' => self::TOP_UP_ID, 'state' => 'open']);

        $purchase = $this->store($rows)->lockForSettlement(self::TOP_UP_ID);

        static::assertInstanceOf(WalletTopUpPurchase::class, $purchase);
        static::assertSame(self::TOP_UP_ID, $purchase->purchaseId());
    }

    public function testUnknownTopUpIsNotSettleable(): void
    {
        $rows = static::createStub(RowLock::class);
        $rows->method('lock')->willReturn(null);

        static::assertNull($this->store($rows)->lockForSettlement(self::TOP_UP_ID));
    }

    private function store(RowLock $rows): WalletTopUpPurchaseStore
    {
        return new WalletTopUpPurchaseStore(
            StaticEntityRepository::of(CustomerWalletTopUpCollection::class),
            static::createStub(WalletLedger::class),
            $rows,
        );
    }
}
