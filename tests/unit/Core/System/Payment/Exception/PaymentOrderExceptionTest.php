<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderException::class)]
class PaymentOrderExceptionTest extends TestCase
{
    public function testAmountMismatchKeepsContract(): void
    {
        $exception = PaymentOrderException::amountMismatch('order-1', 100, 90);

        static::assertSame(PaymentOrderException::class, $exception::class);
        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame('PAYMENT__AMOUNT_MISMATCH', $exception->getErrorCode());
        static::assertSame('The notified amount of "order-1" is 90 but 100 was expected.', $exception->getMessage());
        static::assertSame(['paymentNo' => 'order-1', 'expected' => 100, 'actual' => 90], $exception->getParameters());
    }
}
