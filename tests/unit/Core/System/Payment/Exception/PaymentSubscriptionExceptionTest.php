<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentSubscriptionException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionException::class)]
class PaymentSubscriptionExceptionTest extends TestCase
{
    public function testPaymentCycleNotExpressibleKeepsContract(): void
    {
        $exception = PaymentSubscriptionException::paymentCycleNotExpressible('alipay', 'month', 1, 29);

        static::assertSame(PaymentSubscriptionException::class, $exception::class);
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        static::assertSame('PAYMENT__CYCLE_NOT_EXPRESSIBLE', $exception->getErrorCode());
        static::assertSame('A subscription charging every 1 month on day 29 of the month cannot be expressed at the provider "alipay": monthly deduction days are limited to the 28th there.', $exception->getMessage());
        static::assertSame(['provider' => 'alipay', 'unit' => 'month', 'count' => 1, 'anchorDayOfMonth' => 29], $exception->getParameters());
    }
}
