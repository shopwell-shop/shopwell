<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\Service\WalletPaymentService;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletPaymentService::class)]
class WalletPaymentServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const CURRENCY_ID = 'currency-id';

    private const ORDER_ID = 'order-id';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testDebitBooksANegativeEntryThatMayNotOverdrawAndOnlyOncePerOrder(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::CONSUME,
                -30.0,
                $this->context,
                self::ORDER_ID,
                null,
                null,
                null,
                true,
                true
            )
            ->willReturn(70.0);

        (new WalletPaymentService($ledger))->debitForOrder(self::ORDER_ID, self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, $this->context);
    }

    public function testRefundBooksAPositiveEntryOncePerOrder(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::REFUND,
                15.0,
                $this->context,
                self::ORDER_ID,
                null,
                null,
                null,
                false,
                true,
                false
            )
            ->willReturn(25.0);

        (new WalletPaymentService($ledger))->refundForOrder(self::ORDER_ID, self::CUSTOMER_ID, self::CURRENCY_ID, 15.0, $this->context);
    }

    /**
     * An order can come back in parts, so a refund of one return must not be skipped because another
     * return of the same order was already paid out - the reference is what identifies the booking.
     */
    public function testRefundWithAReferenceIsBookedOncePerReturn(): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->once())
            ->method('book')
            ->with(
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                WalletTransactionTypes::REFUND,
                15.0,
                $this->context,
                self::ORDER_ID,
                'RT-1',
                null,
                null,
                false,
                false,
                true
            )
            ->willReturn(25.0);

        (new WalletPaymentService($ledger))->refundForOrder(
            self::ORDER_ID,
            self::CUSTOMER_ID,
            self::CURRENCY_ID,
            15.0,
            $this->context,
            'RT-1'
        );
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
    public function testDebitRefusesNonPositiveAmounts(float $amount): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->never())->method('book');

        $this->expectExceptionObject(WalletException::invalidAmount($amount));

        (new WalletPaymentService($ledger))->debitForOrder(self::ORDER_ID, self::CUSTOMER_ID, self::CURRENCY_ID, $amount, $this->context);
    }

    #[DataProvider('nonPositiveAmounts')]
    public function testRefundRefusesNonPositiveAmounts(float $amount): void
    {
        $ledger = $this->createMock(WalletLedger::class);
        $ledger->expects($this->never())->method('book');

        $this->expectExceptionObject(WalletException::invalidAmount($amount));

        (new WalletPaymentService($ledger))->refundForOrder(self::ORDER_ID, self::CUSTOMER_ID, self::CURRENCY_ID, $amount, $this->context);
    }
}
