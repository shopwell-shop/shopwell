<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAppException::class)]
class PaymentAppExceptionTest extends TestCase
{
    public function testRateLimitExceededIncludesTheRetryWindow(): void
    {
        $exception = PaymentAppException::rateLimitExceeded(42);
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame('PAYMENT__APP_RATE_LIMIT_EXCEEDED', $exception->getErrorCode());
        static::assertSame(['seconds' => 42], $exception->getParameters());
        static::assertSame(['Retry-After' => '42'], $exception->getHeaders());
    }

    public function testMissingAuthParametersKeepsContract(): void
    {
        $exception = PaymentAppException::missingAuthParameters(['app-key', 'signature']);

        static::assertSame(PaymentAppException::class, $exception::class);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        static::assertSame('PAYMENT__MISSING_AUTH_PARAMETERS', $exception->getErrorCode());
        static::assertSame('Missing authentication parameter(s): app-key, signature', $exception->getMessage());
        static::assertSame(['missing' => 'app-key, signature'], $exception->getParameters());
    }
}
