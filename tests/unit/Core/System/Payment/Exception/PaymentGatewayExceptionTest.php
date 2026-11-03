<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentGatewayException::class)]
class PaymentGatewayExceptionTest extends TestCase
{
    public function testGatewayResultMissingKeepsContractAndFacadeCompatibility(): void
    {
        $exception = PaymentGatewayException::gatewayResultMissing('alipay');

        static::assertSame(PaymentGatewayException::class, $exception::class);
        static::assertSame(Response::HTTP_BAD_GATEWAY, $exception->getStatusCode());
        static::assertSame('PAYMENT__GATEWAY_RESULT_MISSING', $exception->getErrorCode());
        static::assertSame('The payment channel "alipay" did not return a result.', $exception->getMessage());
        static::assertSame(['channel' => 'alipay'], $exception->getParameters());

        $facade = PaymentException::gatewayResultMissing('alipay');

        static::assertSame(PaymentGatewayException::class, $facade::class);
        static::assertEquals($exception, $facade);
    }
}
