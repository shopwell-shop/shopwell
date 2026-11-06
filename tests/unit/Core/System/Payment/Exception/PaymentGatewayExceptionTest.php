<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentGatewayException::class)]
class PaymentGatewayExceptionTest extends TestCase
{
    public function testGatewayResultMissingKeepsContract(): void
    {
        $exception = PaymentGatewayException::gatewayResultMissing('alipay');

        static::assertSame(PaymentGatewayException::class, $exception::class);
        static::assertSame(Response::HTTP_BAD_GATEWAY, $exception->getStatusCode());
        static::assertSame('PAYMENT__GATEWAY_RESULT_MISSING', $exception->getErrorCode());
        static::assertSame('The payment channel "alipay" did not return a result.', $exception->getMessage());
        static::assertSame(['channel' => 'alipay'], $exception->getParameters());
    }

    public function testGatewayErrorKeepsThePreviousFailure(): void
    {
        $previous = new \RuntimeException('connection interrupted');
        $exception = PaymentGatewayException::gatewayError('alipay', 'no response', $previous);

        static::assertSame(PaymentGatewayException::class, $exception::class);
        static::assertSame(Response::HTTP_BAD_GATEWAY, $exception->getStatusCode());
        static::assertSame('PAYMENT__GATEWAY_ERROR', $exception->getErrorCode());
        static::assertSame(['channel' => 'alipay', 'reason' => 'no response'], $exception->getParameters());
        static::assertSame($previous, $exception->getPrevious());
    }

    public function testMissingCredentialsKeepsContract(): void
    {
        $exception = PaymentGatewayException::missingCredentials('alipay', 'app_id');

        static::assertSame(PaymentGatewayException::class, $exception::class);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('PAYMENT__MISSING_CREDENTIALS', $exception->getErrorCode());
        static::assertSame(['channel' => 'alipay', 'field' => 'app_id'], $exception->getParameters());
    }
}
