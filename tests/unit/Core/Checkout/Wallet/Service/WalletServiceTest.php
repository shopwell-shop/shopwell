<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The rules of the wallet are tested here, the balances and their locking in
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Wallet\Service\WalletLedgerTest}.
 *
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

    private StaticSystemConfigService $systemConfigService;

    private WalletLedger $ledger;

    private WalletService $walletService;

    private Context $context;

    protected function setUp(): void
    {
        $this->walletRepository = StaticEntityRepository::of(CustomerWalletCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->ledger = static::createStub(WalletLedger::class);
        $this->context = Context::createDefaultContext();

        $this->walletService = new WalletService(
            $this->walletRepository,
            $this->ledger,
            $this->systemConfigService,
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

    public function testProvisionAccountOpensAWalletInTheDefaultCurrency(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        $this->walletService->provisionAccount(self::CUSTOMER_ID, $this->context);

        $wallet = $this->walletRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $wallet['customerId']);
        static::assertSame(Defaults::CURRENCY, $wallet['currencyId']);
        static::assertSame(0.0, $wallet['balance']);
        static::assertSame(0.0, $wallet['lockedBalance']);
    }

    public function testProvisionAccountKeepsAnExistingWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(42.5)]));

        $this->walletService->provisionAccount(self::CUSTOMER_ID, $this->context);

        static::assertSame([], $this->walletRepository->creates);
        static::assertSame([], $this->walletRepository->updates);
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

    public function testTopUpDelegatesToTheLedger(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_TOP_UP,
                25.0,
                $this->context,
                null,
                'external-ref'
            )
            ->willReturn(25.0);

        static::assertSame(25.0, $this->service($ledger)->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 25.0, 'external-ref', $this->context));
    }

    public function testCreditPaidTopUpIgnoresTheConfiguredMinimum(): void
    {
        // the limit may have moved while the payer was in the payment interface, a payment that
        // really happened must still be credited
        $this->systemConfigService->set(WalletService::CONFIG_KEY_MIN_TOP_UP_AMOUNT, 100.0);

        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_TOP_UP,
                5.0,
                $this->context,
                null,
                'top-up-id'
            )
            ->willReturn(5.0);

        $this->service($ledger)->creditPaidTopUp(self::CUSTOMER_ID, self::CURRENCY_ID, 5.0, 'top-up-id', $this->context);
    }

    public function testCreditPaidTopUpRejectsNonPositiveAmounts(): void
    {
        $this->expectExceptionObject(WalletException::invalidAmount(0.0));

        $this->walletService->creditPaidTopUp(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'top-up-id', $this->context);
    }

    public function testConsumeBooksANegativeEntryThatMayNotOverdraw(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_CONSUME,
                -30.0,
                $this->context,
                'order-1',
                null,
                null,
                null,
                true,
                true
            )
            ->willReturn(70.0);

        $this->service($ledger)->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, 'order-1', $this->context);
    }

    public function testConsumeWithoutAnOrderIsNotBookedOncePerOrder(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_CONSUME,
                -30.0,
                $this->context,
                null,
                null,
                null,
                null,
                true,
                false
            )
            ->willReturn(70.0);

        $this->service($ledger)->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, null, $this->context);
    }

    public function testConsumeThrowsForNonPositiveAmounts(): void
    {
        $this->expectExceptionObject(WalletException::invalidAmount(0.0));

        $this->walletService->consume(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'order-1', $this->context);
    }

    public function testRefundBooksPositiveEntry(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_REFUND,
                15.0,
                $this->context,
                'order-1'
            )
            ->willReturn(25.0);

        $this->service($ledger)->refund(self::CUSTOMER_ID, self::CURRENCY_ID, 15.0, 'order-1', $this->context);
    }

    public function testRefundThrowsForNonPositiveAmount(): void
    {
        $this->expectExceptionObject(WalletException::invalidAmount(0.0));

        $this->walletService->refund(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'order-1', $this->context);
    }

    public function testAdjustBooksManualCorrectionThatMayNotOverdraw(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletService::TRANSACTION_TYPE_ADJUST,
                -50.0,
                $this->context,
                null,
                null,
                'correction',
                'admin-id',
                true
            )
            ->willReturn(30.0);

        $this->service($ledger)->adjust(self::CUSTOMER_ID, self::CURRENCY_ID, -50.0, 'correction', 'admin-id', $this->context);
    }

    public function testAdjustIgnoresZeroAmount(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->never())->method('book');

        $this->service($ledger)->adjust(self::CUSTOMER_ID, self::CURRENCY_ID, 0.0, 'noop', null, $this->context);
    }

    /**
     * The service under test with the double the scenario needs - the shared one of setUp() only
     * answers, it is no mock.
     */
    private function service(?WalletLedger $ledger = null): WalletService
    {
        return new WalletService(
            $this->walletRepository,
            $ledger ?? $this->ledger,
            $this->systemConfigService,
        );
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
