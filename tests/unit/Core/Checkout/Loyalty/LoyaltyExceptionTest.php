<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyException::class)]
class LoyaltyExceptionTest extends TestCase
{
    public function testInvalidPoints(): void
    {
        $exception = LoyaltyException::invalidPoints(5);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_INVALID_POINTS', $exception->getErrorCode());
        static::assertSame('The number of points "5" is invalid. Points must be a positive integer.', $exception->getMessage());
    }

    public function testInsufficientPoints(): void
    {
        $exception = LoyaltyException::insufficientPoints('customer-id', 100, 50);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_INSUFFICIENT_POINTS', $exception->getErrorCode());
        static::assertSame('Customer "customer-id" has 50 points available, but 100 points were requested.', $exception->getMessage());
    }

    public function testBelowMinimumPoints(): void
    {
        $exception = LoyaltyException::belowMinimumPoints(50, 100);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_BELOW_MINIMUM_POINTS', $exception->getErrorCode());
        static::assertSame('At least 100 points must be redeemed, but only 50 points were requested.', $exception->getMessage());
    }

    public function testExceedsMaxRedeemablePercent(): void
    {
        $exception = LoyaltyException::exceedsMaxRedeemablePercent(50.0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_EXCEEDS_MAX_REDEEMABLE', $exception->getErrorCode());
        static::assertSame('The requested points exceed the maximum of 50% of the order total that may be paid with points.', $exception->getMessage());
    }
}
