<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Service\WalletAdjustmentService;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletAdjustmentService::class)]
class WalletAdjustmentServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const CURRENCY_ID = 'currency-id';

    private StaticSystemConfigService $systemConfigService;

    private Context $context;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->context = Context::createDefaultContext();
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

        $this->service()->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, $amount, null, $this->context);
    }

    public function testTopUpThrowsWhenBelowConfiguredMinimum(): void
    {
        $this->systemConfigService->set(WalletSettings::CONFIG_KEY_MIN_TOP_UP_AMOUNT, 10.0);

        $this->expectExceptionObject(WalletException::belowMinimumTopUp(5.0, 10.0));

        $this->service()->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 5.0, null, $this->context);
    }

    public function testTopUpDelegatesToTheLedger(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::TOP_UP,
                25.0,
                $this->context,
                null,
                'external-ref'
            )
            ->willReturn(25.0);

        static::assertSame(25.0, $this->service($ledger)->topUp(self::CUSTOMER_ID, self::CURRENCY_ID, 25.0, 'external-ref', $this->context));
    }

    public function testAdjustBooksACorrectionThatMayNotOverdraw(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::ADJUST,
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

    private function service(?WalletLedger $ledger = null): WalletAdjustmentService
    {
        return new WalletAdjustmentService(
            $ledger ?? static::createStub(WalletLedger::class),
            new WalletSettings($this->systemConfigService),
        );
    }
}
