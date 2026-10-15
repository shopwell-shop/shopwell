<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Event\WalletBalanceChangedEvent;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletService::class)]
class WalletServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const CURRENCY_ID = 'currency-id';

    private const WALLET_ID = 'wallet-id';

    /**
     * @var StaticEntityRepository<CustomerWalletCollection>
     */
    private StaticEntityRepository $walletRepository;

    /**
     * @var StaticEntityRepository<CustomerWalletTransactionCollection>
     */
    private StaticEntityRepository $walletTransactionRepository;

    private StaticSystemConfigService $systemConfigService;

    private EventDispatcher $eventDispatcher;

    private WalletService $walletService;

    private Context $context;

    protected function setUp(): void
    {
        $this->walletRepository = StaticEntityRepository::of(CustomerWalletCollection::class);
        $this->walletTransactionRepository = StaticEntityRepository::of(CustomerWalletTransactionCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();

        $this->walletService = new WalletService(
            $this->walletRepository,
            $this->walletTransactionRepository,
            $this->systemConfigService,
            $this->eventDispatcher,
        );
    }

    public function testGetBalanceReturnsZeroWithoutWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        static::assertSame(0.0, $this->walletService->getBalance(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context));
    }

    public function testGetBalanceReturnsStoredBalance(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(42.5)]));

        static::assertSame(42.5, $this->walletService->getBalance(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context));
    }

    /**
     * @return iterable<string, array{amount: float}>
     */
    public static function nonPositiveAmounts(): iterable
    {
        yield 'zero' => ['amount' => 0.0];
        yield 'negative' => ['amount' => -5.0];
    }

    #[DataProvider('nonPositiveAmounts')]
    public function testTopUpThrowsForNonPositiveAmount(float $amount): void
    {
        $this->expectExceptionObject(WalletException::invalidAmount($amount));

        $this->walletService->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, $amount, null, $this->context);
    }

    public function testTopUpThrowsWhenBelowConfiguredMinimum(): void
    {
        $this->systemConfigService->set(WalletService::CONFIG_KEY_MIN_TOP_UP_AMOUNT, 10.0);

        $this->expectExceptionObject(WalletException::belowMinimumTopUp(5.0, 10.0));

        $this->walletService->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 5.0, null, $this->context);
    }

    public function testTopUpCreatesWalletWhenCustomerHasNone(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        $this->walletService->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 25.0, 'external-ref', $this->context);

        $wallet = $this->walletRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $wallet['customerId']);
        static::assertSame(self::CURRENCY_ID, $wallet['currencyId']);
        static::assertSame(0.0, $wallet['balance']);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(WalletService::TRANSACTION_TYPE_TOP_UP, $transaction['type']);
        static::assertSame(25.0, $transaction['amount']);
        static::assertSame(25.0, $transaction['balanceAfter']);
        static::assertSame('external-ref', $transaction['externalReference']);

        static::assertSame(25.0, $this->walletRepository->updates[0][0]['balance']);
    }

    public function testTopUpBooksOntoExistingWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(10.0)]));

        $this->walletService->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 5.0, null, $this->context);

        static::assertSame(self::WALLET_ID, $this->walletTransactionRepository->creates[0][0]['customerWalletId']);
        static::assertSame(15.0, $this->walletTransactionRepository->creates[0][0]['balanceAfter']);
        static::assertSame(15.0, $this->walletRepository->updates[0][0]['balance']);
    }

    public function testTopUpDispatchesBalanceChangedEvent(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(10.0)]));

        $events = [];
        $this->eventDispatcher->addListener(WalletBalanceChangedEvent::class, static function (WalletBalanceChangedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->walletService->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 5.0, null, $this->context);

        static::assertCount(1, $events);
        static::assertSame(self::CUSTOMER_ID, $events[0]->getCustomerId());
        static::assertSame(self::CURRENCY_ID, $events[0]->getCurrencyId());
        static::assertSame(WalletService::TRANSACTION_TYPE_TOP_UP, $events[0]->getType());
        static::assertSame(5.0, $events[0]->getAmount());
        static::assertSame(15.0, $events[0]->getBalanceAfter());
    }

    public function testConsumeThrowsWhenCustomerHasNoWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        $this->expectExceptionObject(WalletException::insufficientBalance(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 0.0));

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 'order-1', $this->context);
    }

    public function testConsumeThrowsWhenBalanceIsInsufficient(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(20.0)]));

        $this->expectExceptionObject(WalletException::insufficientBalance(self::CUSTOMER_ID, self::CURRENCY_ID, 50.0, 20.0));

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 50.0, 'order-1', $this->context);
    }

    public function testConsumeIsIgnoredForAnOrderThatWasAlreadyDebited(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(100.0)]));
        $this->walletTransactionRepository->addSearch(['existing-transaction']);

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 'order-1', $this->context);

        static::assertSame([], $this->walletTransactionRepository->creates);
    }

    public function testConsumeBooksNegativeTransactionWithoutOrder(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(100.0)]));

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, null, $this->context);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(WalletService::TRANSACTION_TYPE_CONSUME, $transaction['type']);
        static::assertSame(-30.0, $transaction['amount']);
        static::assertSame(70.0, $transaction['balanceAfter']);
        static::assertSame(70.0, $this->walletRepository->updates[0][0]['balance']);
    }

    public function testConsumeBooksNegativeTransactionForANewOrder(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(100.0)]));
        $this->walletTransactionRepository->addSearch([]);

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 'order-1', $this->context);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(-30.0, $transaction['amount']);
        static::assertSame('order-1', $transaction['orderId']);
    }

    public function testRefundThrowsForNonPositiveAmount(): void
    {
        $this->expectExceptionObject(WalletException::invalidAmount(0.0));

        $this->walletService->refund(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'order-1', $this->context);
    }

    public function testRefundBooksPositiveTransaction(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(10.0)]));

        $this->walletService->refund(self::CUSTOMER_ID, self::CURRENCY_ID, 15.0, 'order-1', $this->context);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(WalletService::TRANSACTION_TYPE_REFUND, $transaction['type']);
        static::assertSame(15.0, $transaction['amount']);
        static::assertSame(25.0, $transaction['balanceAfter']);
        static::assertSame('order-1', $transaction['orderId']);
    }

    public function testAdjustThrowsWhenResultingBalanceWouldBeNegative(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(30.0)]));

        $this->expectExceptionObject(WalletException::insufficientBalance(self::CUSTOMER_ID, self::CURRENCY_ID, 50.0, 30.0));

        $this->walletService->adjust(self::CUSTOMER_ID, self::CURRENCY_ID, -50.0, 'correction', 'admin-id', $this->context);
    }

    public function testAdjustBooksManualCorrection(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(30.0)]));

        $this->walletService->adjust(self::CUSTOMER_ID, self::CURRENCY_ID, 20.0, 'goodwill', 'admin-id', $this->context);

        $transaction = $this->walletTransactionRepository->creates[0][0];
        static::assertSame(WalletService::TRANSACTION_TYPE_ADJUST, $transaction['type']);
        static::assertSame(20.0, $transaction['amount']);
        static::assertSame(50.0, $transaction['balanceAfter']);
        static::assertSame('goodwill', $transaction['comment']);
        static::assertSame('admin-id', $transaction['administratorId']);
    }

    public function testAdjustIgnoresZeroAmount(): void
    {
        $this->walletService->adjust(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'noop', null, $this->context);

        static::assertSame([], $this->walletTransactionRepository->creates);
        static::assertSame([], $this->walletRepository->searches);
    }

    public function testProvisionAccountOpensAWalletInTheDefaultCurrency(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        $this->walletService->provisionAccount(self::CUSTOMER_ID, $this->context);

        $wallet = $this->walletRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $wallet['customerId']);
        static::assertSame(Defaults::CURRENCY, $wallet['currencyId']);
        static::assertSame(0.0, $wallet['balance']);
        static::assertSame(0.0, $wallet['lockedBalance']);

        // the balance belongs to the ledger, opening the account books nothing
        static::assertSame([], $this->walletTransactionRepository->creates);
    }

    public function testProvisionAccountKeepsAnExistingWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(42.5)]));

        $this->walletService->provisionAccount(self::CUSTOMER_ID, $this->context);

        static::assertSame([], $this->walletRepository->creates);
        static::assertSame([], $this->walletRepository->updates);
    }

    private function createWallet(float $balance): CustomerWalletEntity
    {
        $wallet = new CustomerWalletEntity();
        $wallet->setId(self::WALLET_ID);
        $wallet->setCustomerId(self::CUSTOMER_ID);
        $wallet->setCurrencyId(self::CURRENCY_ID);
        $wallet->setBalance($balance);

        return $wallet;
    }
}
