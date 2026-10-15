<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletException::class)]
class WalletExceptionTest extends TestCase
{
    public function testInvalidAmount(): void
    {
        $exception = WalletException::invalidAmount(0.0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__WALLET_INVALID_AMOUNT', $exception->getErrorCode());
        static::assertSame('The amount "0" is invalid. The amount must be greater than zero.', $exception->getMessage());
    }

    public function testInsufficientBalance(): void
    {
        $exception = WalletException::insufficientBalance('customer-id', 'currency-id', 100.0, 50.0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__WALLET_INSUFFICIENT_BALANCE', $exception->getErrorCode());
        static::assertSame('The wallet of customer "customer-id" in currency "currency-id" holds 50, but 100 was requested.', $exception->getMessage());
    }

    public function testBelowMinimumTopUp(): void
    {
        $exception = WalletException::belowMinimumTopUp(5.0, 10.0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__WALLET_BELOW_MINIMUM_TOP_UP', $exception->getErrorCode());
        static::assertSame('A top-up of at least 10 is required, but only 5 was given.', $exception->getMessage());
    }

    public function testExceedsMaxPaymentPercent(): void
    {
        $exception = WalletException::exceedsMaxPaymentPercent(50.0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__WALLET_EXCEEDS_MAX_PAYMENT', $exception->getErrorCode());
        static::assertSame('The requested wallet payment exceeds the maximum of 50% of the order total that may be paid with the wallet.', $exception->getMessage());
    }
}
