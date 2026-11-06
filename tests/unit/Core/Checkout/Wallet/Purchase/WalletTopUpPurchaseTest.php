<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Purchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchase;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpPurchase::class)]
class WalletTopUpPurchaseTest extends TestCase
{
    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<CustomerWalletTopUpCollection>
     */
    private StaticEntityRepository $topUpRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->topUpRepository = StaticEntityRepository::of(CustomerWalletTopUpCollection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testSettleCreditsTheWalletAndRecordsThePayment(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::TOP_UP,
                128.0,
                $this->context,
                null,
                self::TOP_UP_ID
            );

        $settledAt = new \DateTimeImmutable('2026-06-01 12:00:00');
        $this->purchase($ledger)->settle('PAY-1', $settledAt, $this->context);

        $update = $this->topUpRepository->updates[0][0];
        static::assertSame(CustomerWalletTopUpEntity::STATE_CREDITED, $update['state']);
        static::assertSame('PAY-1', $update['paymentNo']);
        static::assertSame($settledAt, $update['paidAt']);
        static::assertSame($settledAt, $update['creditedAt']);
    }

    public function testFailKeepsThePaidMoneyVisible(): void
    {
        $failedAt = new \DateTimeImmutable('2026-06-01 12:00:00');
        $this->purchase(static::createStub(WalletLedger::class))->fail('PAY-1', $failedAt, $this->context);

        $update = $this->topUpRepository->updates[0][0];
        static::assertSame(CustomerWalletTopUpEntity::STATE_FAILED, $update['state']);
        static::assertSame($failedAt, $update['paidAt']);
    }

    public function testAnAlreadyCreditedTopUpIsSettled(): void
    {
        static::assertTrue($this->purchase(static::createStub(WalletLedger::class), CustomerWalletTopUpEntity::STATE_CREDITED)->isSettled());
    }

    public function testAnOpenTopUpIsNotSettled(): void
    {
        static::assertFalse($this->purchase(static::createStub(WalletLedger::class), CustomerWalletTopUpEntity::STATE_OPEN)->isSettled());
    }

    /**
     * @return iterable<string, array{currency: string, amount: float, minor: int}>
     */
    public static function amounts(): iterable
    {
        yield 'two decimals' => ['currency' => 'CNY', 'amount' => 128.5, 'minor' => 12850];
        yield 'no decimals' => ['currency' => 'JPY', 'amount' => 128.0, 'minor' => 128];
    }

    #[DataProvider('amounts')]
    public function testAmountIsConvertedWithTheExponentOfTheCurrency(string $currency, float $amount, int $minor): void
    {
        $purchase = $this->purchase(static::createStub(WalletLedger::class), CustomerWalletTopUpEntity::STATE_OPEN, $currency, $amount);

        static::assertSame($minor, $purchase->amountMinor());
    }

    private function purchase(
        WalletLedger $ledger,
        string $state = CustomerWalletTopUpEntity::STATE_OPEN,
        string $currency = 'CNY',
        float $amount = 128.0,
    ): WalletTopUpPurchase {
        return new WalletTopUpPurchase(
            [
                'id' => self::TOP_UP_ID,
                'customer_id' => self::CUSTOMER_ID,
                'currency_id' => self::CURRENCY_ID,
                'currency' => $currency,
                'amount' => $amount,
                'state' => $state,
            ],
            $this->topUpRepository,
            $ledger,
        );
    }
}
