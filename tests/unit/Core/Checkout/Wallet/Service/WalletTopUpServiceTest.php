<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The top-up intent is tested here, the money itself moves on settlement - see
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseTest}.
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

    /**
     * @var StaticEntityRepository<CustomerWalletTopUpCollection>
     */
    private StaticEntityRepository $topUpRepository;

    private StaticSystemConfigService $systemConfigService;

    private StorefrontPaymentService&Stub $payments;

    private WalletTopUpService $topUpService;

    private Context $context;

    protected function setUp(): void
    {
        $this->topUpRepository = StaticEntityRepository::of(CustomerWalletTopUpCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->payments = static::createStub(StorefrontPaymentService::class);
        $this->context = Context::createDefaultContext();

        $this->topUpService = $this->service();
    }

    public function testOptionsReportLimitsPresetsAndPayableChannels(): void
    {
        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_MIN_TOP_UP_AMOUNT, 10.0);
        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_MAX_TOP_UP_AMOUNT, 5000.0);

        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->once())
            ->method('channels')
            ->with('CNY', 'web')
            ->willReturn([['channel' => 'alipay_h5', 'label' => '支付宝', 'provider' => 'alipay', 'provider_label' => '支付宝', 'flow' => 'redirect']]);

        $options = $this->service($payments)->options('CNY', 'web');

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

        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_TOP_UP_PRESETS, '300, 100 ,abc,, -5, 100');

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
                static::stringStartsWith('wallet_top_up:'),
                'https://shop.test/account/wallet',
                '10.0.0.1',
                'web',
            )
            ->willReturn(['payment_no' => 'PAY-1', 'channel' => 'alipay_h5', 'params' => ['url' => 'https://pay.test']]);

        $result = $this->service($payments)->create(
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

    public function testCreateIsRejectedWhenTheWalletIsDisabled(): void
    {
        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_ENABLED, false);

        $payments = $this->createMock(StorefrontPaymentService::class);
        $payments->expects($this->never())->method('open');

        $this->expectExceptionObject(WalletException::walletDisabled());

        $this->service($payments)->create(
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

        $this->service($payments)->create(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', $amount, 'alipay_h5', null, null, null, $this->context);
    }

    public function testCreateRefusesAnAmountAboveTheConfiguredMaximum(): void
    {
        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_MAX_TOP_UP_AMOUNT, 500.0);

        $this->expectExceptionObject(WalletException::topUpAboveMaximum(600.0, 500.0));

        $this->topUpService->create(self::CUSTOMER_ID, self::CURRENCY_ID, 'CNY', 600.0, 'alipay_h5', null, null, null, $this->context);
    }

    public function testRecentAnswersTheNewestTopUps(): void
    {
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection([
            $this->createTopUp(100.0, CustomerWalletTopUpEntity::STATE_OPEN),
        ]));

        $recent = $this->topUpService->recentTopUps(self::CUSTOMER_ID, 5, $this->context);

        static::assertCount(1, $recent);
        static::assertSame(100.0, $recent[0]->getAmount());
        static::assertSame(CustomerWalletTopUpEntity::STATE_OPEN, $recent[0]->getState());
    }

    public function testFindThrowsForATopUpOfAnotherCustomer(): void
    {
        $this->topUpRepository->addSearch(new CustomerWalletTopUpCollection());

        $this->expectExceptionObject(WalletException::topUpNotFound(self::TOP_UP_ID));

        $this->topUpService->find(self::TOP_UP_ID, self::CUSTOMER_ID, $this->context);
    }

    private function service(?StorefrontPaymentService $payments = null): WalletTopUpService
    {
        return new WalletTopUpService(
            $this->topUpRepository,
            $payments ?? $this->payments,
            new WalletSettings($this->systemConfigService),
            new PurchaseAttach('wallet_top_up:'),
        );
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
