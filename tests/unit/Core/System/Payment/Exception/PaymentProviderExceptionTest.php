<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderException::class)]
class PaymentProviderExceptionTest extends TestCase
{
    public function testUnknownProviderKeepsContractAndFacadeCompatibility(): void
    {
        $exception = PaymentProviderException::unknownProvider('alipay');

        static::assertSame(PaymentProviderException::class, $exception::class);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame('PAYMENT__UNKNOWN_PROVIDER', $exception->getErrorCode());
        static::assertSame('The payment provider "alipay" is unknown.', $exception->getMessage());
        static::assertSame(['technicalName' => 'alipay'], $exception->getParameters());

        $facade = PaymentException::unknownProvider('alipay');

        static::assertSame(PaymentProviderException::class, $facade::class);
        static::assertEquals($exception, $facade);
    }
}
