<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * The top-up intent and its settlement are tested here, the money itself moves in
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Wallet\Service\WalletServiceTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpService::class)]
class WalletTopUpServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TOP_UP_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CURRENCY_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NOW = '2026-06-01 12:00:00';

    /**
     * @var StaticEntityRepository<CustomerWalletTopUpCollection>
     */
    private StaticEntityRepository $topUpRepository;

    private StaticSystemConfigService $systemConfigService;

    private WalletService $walletService;

    private StorefrontPaymentService&Stub $payments;

    private Connection&Stub $connection;

    private WalletTopUpService $topUpService;

    private Context $context;

    protected function setUp(): void
    {
        $this->topUpRepository = StaticEntityRepository::of(CustomerWalletTopUpCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->walletService = static::createStub(WalletService::class);
        $this->payments = static::createStub(StorefrontPaymentService::class);
        $this->connection = static::createStub(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->connection->method('transactional')->willReturnCallback(static fn (callable $callback) => $callback());

        $clock = new MockClock(self::NOW);

        $this->topUpService = new WalletTopUpService(
            $this->topUpRepository,
            $this->walletService,
            $this->payments,
            $this->systemConfigService,
            $this->connection,
            $clock,
        );
    }

    public function testOptionsReportLimitsPresetsAndPayableChannels(): void
    {
        $this->systemConfigService->set(WalletService::CONFIG_KEY_MIN_TOP_UP_AMOUNT, 10.0);
        $this->systemConfigService->set(WalletTopUpService::CONFIG_KEY_MAX_TOP_UP_AMOUNT, 5000.0);

        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->once())
            ->method('channels')
            ->with('CNY', 'web')
            ->willReturn([['channel' => 'alipay_h5', 'label' => '支付宝', 'provider' => 'alipay', 'provider_label' => '支付宝', 'flow' => 'redirect']]);

        $options = $this->service(payments: $payments)->options('CNY', 'web');

        static::assertSame(10.0, $options['min']);
        static::assertSame(5000.0, $options['max']);
        static::assertSame([100.0, 200.0, 500.0, 1000.0, 2000.0], $options['presets']);
        static::assertCount(1, $options['channels']);
    }

    public function testPresetsFallBackToTheDefaultsAndDropGarbage(): void
    {
        $this->payments->method('channels')->willReturn([]);

        static::assertSame(
            [100.0, 200.0, 500.0, 1000.0, 2000.0],
            $this->topUpService->options('CNY', null)['presets']
        );

        $this->systemConfigService->set(WalletTopUpService::CONFIG_KEY_PRESETS, '300, 100 ,abc,, -5, 100');

        static::assertSame([100.0, 300.0], $this->topUpService->options('CNY', null)['presets']);
    }

    public function testCreateWritesTheIntentAndOpensThePayment(): void
    {
        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->once())
            ->method('open')
            ->with(
                static::isString(),
                'alipay_h5',
                // the amount is handed to the channel in the smallest unit of the currency
                12800,
                'CNY',
                'Wallet top-up',
                static::stringStartsWith(WalletTopUpService::ATTACH_PREFIX),
                'https://shop.test/account/wallet',
                '10.0.0.1',
                'web',
            )
            ->willReturn(['payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => ['url' => 'https://pay.test']]);

        $result = $this->service(payments: $payments)->create(
            self::CUSTOMER_ID,
            self::CURRENCY_ID,
            'cny',
            128.0,
            'alipay_h5',
            'https://shop.test/account/wallet',
            '10.0.0.1',
            'web',
            $this->context,
        );

        static::assertSame(128.0, $result['amount']);
        static::assertSame('CNY', $result['currency']);
        static::assertSame('PAY-1', $result['payment_no']);

        $created = $this->topUpRepository->creates[0][0];
        static::assertSame($result['id'], $created['id']);
        static::assertSame('CNY', $created['currency']);
        static::assertSame(CustomerWalletTopUpEntity::STATE_OPEN, $created['state']);
        static::assertSame('alipay_h5', $created['channelTechnicalName']);

        static::assertSame('PAY-1', $this->topUpRepository->updates[0][0]['paymentNo']);
    }

    public function testCreateKeepsAFailedAttemptVisible(): void
    {
        $this->payments->method('open')->willThrowException(new \RuntimeException('channel is down'));

        try {
            $this->topUpService->create(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                'CNY',
                100.0,
                'alipay_h5',
                null,
                null,
                null,
                $this->context,
            );

            static::fail('The exception of the channel has to reach the caller');
        } catch (\RuntimeException) {
            // expected
        }

        static::assertSame(CustomerWalletTopUpEntity::STATE_FAILED, $this->topUpRepository->updates[0][0]['state']);
    }

    /**
     * @return iterable<string, array{amount: float}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['amount' => 0.0];
        yield 'negative' => ['amount' => -10.0];
    }

    #[DataProvider('invalidAmounts')]
    public function testCreateRefusesNonPositiveAmounts(float $amount): void
    {
        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->never())->method('open');

        $this->expectExceptionObject(WalletException::invalidAmount($amount));

        $this->service(payments: $payments)->create(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', $amount, 'alipay_h5', null, null, null, $this->context);
    }

    public function testCreateRefusesAnAmountAboveTheConfiguredMaximum(): void
    {
        $this->systemConfigService->set(WalletTopUpService::CONFIG_KEY_MAX_TOP_UP_AMOUNT, 500.0);

        $this->expectExceptionObject(WalletException::topUpAboveMaximum(600.0, 500.0));

        $this->topUpService->create(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', 600.0, 'alipay_h5', null, null, null, $this->context);
    }

    public function testRecentAnswersTheNewestTopUps(): void
    {
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection([
            $this->createTopUp(100.0, CustomerWalletTopUpEntity::STATE_OPEN),
        ]));

        $recent = $this->topUpService->recent(self::CUSTOMER_ID, 5, $this->context);

        static::assertCount(1, $recent);
        static::assertSame(100.0, $recent[0]['amount']);
        static::assertSame(CustomerWalletTopUpEntity::STATE_OPEN, $recent[0]['state']);
    }

    public function testStatusThrowsForATopUpOfAnotherCustomer(): void
    {
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection());

        $this->expectExceptionObject(WalletException::topUpNotFound(self::TOP_UP_ID));

        $this->topUpService->status(self::TOP_UP_ID, self::CUSTOMER_ID, $this->context);
    }

    public function testSettleCreditsTheWalletOnce(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->topUpRow(128.0, CustomerWalletTopUpEntity::STATE_OPEN));

        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->once())
            ->method('creditPaidTopUp')
            ->with(self::CUSTOMER_ID, self::CURRENCY_ID, 128.0, self::TOP_UP_ID, $this->context);

        static::assertTrue($this->service(walletService: $walletService)->settle(self::TOP_UP_ID, 12800, 'PAY-1', $this->context));
        static::assertSame(CustomerWalletTopUpEntity::STATE_CREDITED, $this->topUpRepository->updates[0][0]['state']);
    }

    public function testSettleDoesNothingForAnAlreadyCreditedTopUp(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->topUpRow(128.0, CustomerWalletTopUpEntity::STATE_CREDITED));

        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->never())->method('creditPaidTopUp');

        static::assertFalse($this->service(walletService: $walletService)->settle(self::TOP_UP_ID, 12800, 'PAY-1', $this->context));
        static::assertSame([], $this->topUpRepository->updates);
    }

    public function testSettleRefusesAnAmountTheCustomerDidNotAgreeTo(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->topUpRow(128.0, CustomerWalletTopUpEntity::STATE_OPEN));

        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->never())->method('creditPaidTopUp');

        static::assertFalse($this->service(walletService: $walletService)->settle(self::TOP_UP_ID, 1, 'PAY-1', $this->context));
        static::assertSame(CustomerWalletTopUpEntity::STATE_FAILED, $this->topUpRepository->updates[0][0]['state']);
    }

    public function testSettleIgnoresAnUnknownTopUp(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);

        static::assertFalse($this->topUpService->settle(self::TOP_UP_ID, 12800, 'PAY-1', $this->context));
        static::assertSame([], $this->topUpRepository->updates);
    }

    /**
     * A currency without a minor unit is charged in whole units - converting with a fixed factor of
     * hundred would charge a hundredth of the intended amount.
     */
    public function testSettleUsesTheExponentOfTheCurrency(): void
    {
        $this->connection->method('fetchAssociative')->willReturn($this->topUpRow(128.0, CustomerWalletTopUpEntity::STATE_OPEN, 'JPY'));

        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->once())
            ->method('creditPaidTopUp')
            ->with(self::CUSTOMER_ID, self::CURRENCY_ID, 128.0, self::TOP_UP_ID, $this->context);

        static::assertTrue($this->service(walletService: $walletService)->settle(self::TOP_UP_ID, 128, 'PAY-1', $this->context));
    }

    public function testSimulateGoesThroughTheSameSettlement(): void
    {
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection([
            $this->createTopUp(50.0, CustomerWalletTopUpEntity::STATE_OPEN),
        ]));
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection([
            $this->createTopUp(50.0, CustomerWalletTopUpEntity::STATE_CREDITED),
        ]));
        $this->connection->method('fetchAssociative')->willReturn($this->topUpRow(50.0, CustomerWalletTopUpEntity::STATE_OPEN));

        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->once())
            ->method('creditPaidTopUp')
            ->with(self::CUSTOMER_ID, self::CURRENCY_ID, 50.0, self::TOP_UP_ID, $this->context);

        $status = $this->service(walletService: $walletService)->simulate(self::TOP_UP_ID, self::CUSTOMER_ID, $this->context);

        static::assertSame(CustomerWalletTopUpEntity::STATE_CREDITED, $status['state']);
    }

    /**
     * The service under test with the doubles the scenario needs - the shared ones of setUp() only
     * answer, they are no mocks.
     */
    private function service(
        ?WalletService $walletService = null,
        ?StorefrontPaymentService $payments = null,
    ): WalletTopUpService {
        return new WalletTopUpService(
            $this->topUpRepository,
            $walletService ?? $this->walletService,
            $payments ?? $this->payments,
            $this->systemConfigService,
            $this->connection,
            new MockClock(self::NOW),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function topUpRow(float $amount, string $state, string $currency = 'CNY'): array
    {
        return [
            'id' => self::TOP_UP_ID,
            'customer_id' => self::CUSTOMER_ID,
            'currency_id' => self::CURRENCY_ID,
            'currency' => $currency,
            'amount' => $amount,
            'state' => $state,
        ];
    }

    private function createTopUp(float $amount, string $state): CustomerWalletTopUpEntity
    {
        $topUp = new CustomerWalletTopUpEntity();
        $topUp->setId(self::TOP_UP_ID);
        $topUp->setCustomerId(self::CUSTOMER_ID);
        $topUp->setCurrencyId(self::CURRENCY_ID);
        $topUp->setCurrency('CNY');
        $topUp->setAmount($amount);
        $topUp->setState($state);

        return $topUp;
    }
}
