<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationException::class)]
class PaymentNotificationExceptionTest extends TestCase
{
    public function testNotificationInvalidKeepsContract(): void
    {
        $exception = PaymentNotificationException::notificationInvalid('alipay', 'bad signature');

        static::assertSame(PaymentNotificationException::class, $exception::class);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('PAYMENT__NOTIFICATION_INVALID', $exception->getErrorCode());
        static::assertSame('The notification of channel "alipay" is invalid: bad signature', $exception->getMessage());
        static::assertSame(['channel' => 'alipay', 'reason' => 'bad signature'], $exception->getParameters());
    }
}
