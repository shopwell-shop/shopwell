<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * The codes keep their historical prefix because the storefront and the administration switch on them.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipException::class)]
class MembershipExceptionTest extends TestCase
{
    public function testPlanNotFound(): void
    {
        $exception = MembershipException::planNotFound('plan-id');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_PLAN_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The membership plan "plan-id" does not exist.', $exception->getMessage());
    }

    public function testPlanNotPurchasable(): void
    {
        $exception = MembershipException::planNotPurchasable('plan-id');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__LOYALTY_PLAN_NOT_PURCHASABLE', $exception->getErrorCode());
        static::assertSame('The membership plan "plan-id" cannot be bought in this currency.', $exception->getMessage());
    }
}
