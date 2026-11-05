<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentRefundRejectedException;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundRejectedException::class)]
class PaymentRefundRejectedExceptionTest extends TestCase
{
    public function testTerminalRejectionsKeepTheGatewayErrorContract(): void
    {
        $exception = PaymentRefundRejectedException::rejected('wechat', 'CLOSED');

        static::assertSame(502, $exception->getStatusCode());
        static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        static::assertStringContainsString('wechat', $exception->getMessage());
        static::assertStringContainsString('CLOSED', $exception->getMessage());
    }
}
