<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Event\WalletBalanceChangedEvent;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletLedger::class)]
class WalletLedgerTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const WALLET_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ORDER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<CustomerWalletCollection>
     */
    private StaticEntityRepository $walletRepository;

    /**
     * @var StaticEntityRepository<CustomerWalletTransactionCollection>
     */
    private StaticEntityRepository $walletTransactionRepository;

    private Connection&Stub $connection;

    /**
     * Answers the wallet row the ledger locks; tests that need another one set it instead of
     * replacing the connection double.
     *
     * @var (\Closure(): mixed)|null
     */
    private ?\Closure $rowResolver = null;

    private EventDispatcher $eventDispatcher;

    private WalletLedger $ledger;

    private Context $context;

    protected function setUp(): void
    {
        $this->walletRepository = StaticEntityRepository::of(CustomerWalletCollection::class);
        $this->walletTransactionRepository = StaticEntityRepository::of(CustomerWalletTransactionCollection::class);
        $this->connection = static::createStub(Connection::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();

        // a real transaction runs the closure inline; the assertions are about what happens inside it
        $this->connection->method('transactional')->willReturnCallback(
            static fn (callable $callback) => $callback()
        );

        $this->connection->method('fetchAssociative')->willReturnCallback(function (): mixed {
            if ($this->rowResolver !== null) {
                return ($this->rowResolver)();
            }

            return ['id' => self::WALLET_ID, 'balance' => 10.0];
        });

        $this->ledger = new WalletLedger(
            $this->walletRepository,
            $this->walletTransactionRepository,
            $this->connection,
            $this->eventDispatcher,
        );
    }

    public function testBooksTheEntryAndMovesTheBalance(): void
    {
        $balanceAfter = $this->ledger->book(
            self::CUSTOMER_ID,
            self::CURRENCY_ID,
            'top_up',
            25.0,
            $this->context,
            externalReference: 'top-up-id'
        );

        static::assertSame(35.0, $balanceAfter);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(self::WALLET_ID, $transaction['customerWalletId']);
        static::assertSame('top_up', $transaction['type']);
        static::assertSame(25.0, $transaction['amount']);
        static::assertSame(35.0, $transaction['balanceAfter']);
        static::assertSame(self::CURRENCY_ID, $transaction['currencyId']);
        static::assertSame('top-up-id', $transaction['externalReference']);

        static::assertSame(self::WALLET_ID, $this->walletRepository->updates[0][0]['id']);
        static::assertSame(35.0, $this->walletRepository->updates[0][0]['balance']);
    }

    public function testAnnouncesTheBookingOnlyAfterItWasWritten(): void
    {
        $events = [];
        $this->eventDispatcher->addListener(WalletBalanceChangedEvent::class, static function (WalletBalanceChangedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->ledger->book(self::CUSTOMER_ID, self::CURRENCY_ID, 'refund', 5.0, $this->context);

        static::assertCount(1, $events);
        static::assertSame(self::CUSTOMER_ID, $events[0]->getCustomerId());
        static::assertSame('refund', $events[0]->getType());
        static::assertSame(15.0, $events[0]->getBalanceAfter());
    }

    public function testOpensTheWalletWhenTheCustomerHasNone(): void
    {
        $calls = 0;
        $this->rowResolver = function () use (&$calls): mixed {
            ++$calls;

            // the first lookup misses, the wallet is created, the second one finds it
            return $calls === 1 ? false : ['id' => self::WALLET_ID, 'balance' => 0.0];
        };

        $this->ledger->book(self::CUSTOMER_ID, self::CURRENCY_ID, 'top_up', 10.0, $this->context);

        $wallet = $this->walletRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $wallet['customerId']);
        static::assertSame(self::CURRENCY_ID, $wallet['currencyId']);
        static::assertSame(0.0, $wallet['balance']);
        static::assertSame(10.0, $this->walletTransactionRepository->creates[0][0]['balanceAfter']);
    }

    public function testRefusesToOverdrawAWallet(): void
    {
        $this->expectExceptionObject(
            WalletException::insufficientBalance(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 10.0)
        );

        try {
            $this->ledger->book(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                'consume',
                -30.0,
                $this->context,
                rejectNegativeBalance: true
            );
        } finally {
            // nothing was written, the balance and the ledger are untouched
            static::assertSame([], $this->walletTransactionRepository->creates);
            static::assertSame([], $this->walletRepository->updates);
        }
    }

    public function testSkipsAnOrderThatWasAlreadyDebited(): void
    {
        // the order was booked before, the unique key answers the lookup
        $this->connection->method('fetchOne')->willReturn(1);

        $balanceAfter = $this->ledger->book(
            self::CUSTOMER_ID,
            self::CURRENCY_ID,
            'consume',
            -30.0,
            $this->context,
            orderId: self::ORDER_ID,
            oncePerOrder: true
        );

        static::assertSame(10.0, $balanceAfter);
        static::assertSame([], $this->walletTransactionRepository->creates);
        static::assertSame([], $this->walletRepository->updates);
    }

    public function testDoesNotLookForADuplicateWithoutAnOrder(): void
    {
        // this is the only test that asserts on the lookup itself, so it brings its own double
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');
        $connection->method('transactional')->willReturnCallback(static fn (callable $callback) => $callback());
        $connection->method('fetchAssociative')->willReturn(['id' => self::WALLET_ID, 'balance' => 10.0]);

        $ledger = new WalletLedger(
            $this->walletRepository,
            $this->walletTransactionRepository,
            $connection,
            $this->eventDispatcher,
        );

        $ledger->book(
            self::CUSTOMER_ID,
            self::CURRENCY_ID,
            'consume',
            -5.0,
            $this->context,
            oncePerOrder: true
        );

        static::assertCount(1, $this->walletTransactionRepository->creates);
    }
}
